<?php

namespace App\Services;

use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrnService
{
    /**
     * GRN header fields an admin edit may change.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = ['supplier', 'note'];

    /**
     * GRN line fields an admin edit may change; quantities and serials stay locked.
     *
     * @var list<string>
     */
    public const EDITABLE_ITEM_FIELDS = ['cost_price', 'selling_price'];

    public function __construct(
        private StockService $stock,
        private SupplierService $suppliers,
        private AuditLogger $audit,
    ) {}

    /**
     * Receive goods in one transaction (SPEC §8.12): a GRN- record with its items, a new batch (and units)
     * per item at the chosen location, raised product counters, an `in` movement per item, and — when a
     * supplier is given — the GRN total added to what we owe them.
     *
     * @param  array{supplier_id?: ?int, location: string, note?: ?string, items: list<array{product_id: int, cost_price: float|string, selling_price?: float|string|null, qty?: ?int, serials?: list<string>}>}  $data
     *
     * @throws ValidationException
     */
    public function receive(array $data, User $receivedBy): Grn
    {
        return DB::transaction(function () use ($data, $receivedBy): Grn {
            $supplier = filled($data['supplier_id'] ?? null)
                ? Supplier::query()->whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail()
                : null;

            $products = Product::query()
                ->whereKey(collect($data['items'])->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = array_map(fn (array $item): array => $this->line($products[$item['product_id']], $item), $data['items']);
            $this->ensureSerialsAreNew($lines);

            $totalCents = array_sum(array_map(fn (array $line): int => $line['qty'] * SupplierService::cents($line['cost_price']), $lines));
            $grnNo = Numbering::next('grn', Numbering::PREFIXES['grn']);
            $note = "Received via {$grnNo}";

            $grn = Grn::query()->create([
                'grn_no' => $grnNo,
                'supplier_id' => $supplier?->id,
                'supplier_name' => $supplier?->name ?? '',
                'total_cost' => $totalCents / 100,
                'received_by_id' => $receivedBy->id,
                'received_by_name' => $receivedBy->name ?: $receivedBy->email,
                'note' => (string) ($data['note'] ?? ''),
                'location' => $data['location'],
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];

                $batch = $this->stock->receiveBatch(
                    $product,
                    $data['location'],
                    $line['qty'],
                    $line['cost_price'],
                    $line['selling_price'],
                    $note,
                    $line['serials'],
                    $supplier?->id,
                );

                $grn->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'qty' => $line['qty'],
                    'cost_price' => $line['cost_price'],
                    'selling_price' => $line['selling_price'],
                    'serials' => $line['serials'],
                    'batch_id' => $batch->id,
                ]);

                $this->stock->recordMovement($product, 'in', $line['qty'], 'grn', $grn->id, $note, $receivedBy, [
                    'location' => $data['location'],
                    'supplier_name' => $supplier?->name,
                ]);
            }

            if ($supplier !== null) {
                $this->suppliers->addPayable($supplier, $totalCents / 100);
            }

            return $grn;
        });
    }

    /**
     * Admin edit of a GRN (SPEC §8.12): supplier, note and each line's cost / selling price. A cost change is
     * also written to the line's batch (and its in-stock units). The GRN total and supplier balances are NOT
     * recomputed. Changed fields are audit logged in the same transaction.
     *
     * @param  array{supplier_id?: ?int, note?: ?string, items?: list<array{id: int, cost_price: float|string, selling_price?: float|string|null}>}  $data
     * @return list<array{field: string, before: mixed, after: mixed}> the changes made
     */
    public function update(Grn $grn, array $data, User $editor): array
    {
        return DB::transaction(function () use ($grn, $data, $editor): array {
            $grn = Grn::query()->whereKey($grn->id)->lockForUpdate()->firstOrFail();
            $items = $grn->items()->lockForUpdate()->get()->keyBy('id');

            $supplierId = filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null;
            $supplierName = match (true) {
                $supplierId === $grn->supplier_id => $grn->supplier_name,
                $supplierId === null => '',
                default => Supplier::query()->whereKey($supplierId)->value('name'),
            };
            $note = (string) ($data['note'] ?? '');

            $changes = $this->audit->diff(
                ['supplier' => $grn->supplier_name, 'note' => $grn->note],
                ['supplier' => $supplierName, 'note' => $note],
                self::EDITABLE_FIELDS,
            );

            foreach ($data['items'] ?? [] as $itemData) {
                $item = $items->get($itemData['id']);

                if ($item !== null) {
                    $changes = [...$changes, ...$this->updateItem($item, $itemData)];
                }
            }

            if ($changes === []) {
                return [];
            }

            $grn->update(['supplier_id' => $supplierId, 'supplier_name' => $supplierName, 'note' => $note]);

            $this->audit->write($grn->getTable(), $grn->id, $grn->grn_no, $changes, $editor);

            return $changes;
        });
    }

    /**
     * Apply a line's new prices, copying a cost change to its batch, and return the line's changes.
     *
     * @param  array{id: int, cost_price: float|string, selling_price?: float|string|null}  $itemData
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    private function updateItem(GrnItem $item, array $itemData): array
    {
        $patch = [
            'cost_price' => $itemData['cost_price'],
            'selling_price' => filled($itemData['selling_price'] ?? null) ? $itemData['selling_price'] : null,
        ];

        $changes = $this->audit->diff($item->only(self::EDITABLE_ITEM_FIELDS), $patch, self::EDITABLE_ITEM_FIELDS);

        if ($changes === []) {
            return [];
        }

        $costChanged = in_array('cost_price', array_column($changes, 'field'), true);

        $item->update($patch);

        if ($costChanged) {
            $batch = ProductBatch::query()->whereKey($item->batch_id)->lockForUpdate()->first();

            $batch?->update(['cost_price' => $patch['cost_price']]);
            $batch?->units()->where('status', 'in_stock')->update(['cost_price' => $patch['cost_price']]);
        }

        return array_map(fn (array $change): array => [...$change, 'field' => "{$item->product_name} · {$change['field']}"], $changes);
    }

    /**
     * Resolve a submitted item against its locked product: serial products receive one unit per serial.
     *
     * @param  array{product_id: int, cost_price: float|string, selling_price?: float|string|null, qty?: ?int, serials?: list<string>}  $item
     * @return array{product: Product, qty: int, cost_price: float, selling_price: ?float, serials: list<string>}
     */
    private function line(Product $product, array $item): array
    {
        $serials = $product->track_serial ? array_values($item['serials'] ?? []) : [];

        return [
            'product' => $product,
            'qty' => $product->track_serial ? count($serials) : (int) $item['qty'],
            'cost_price' => SupplierService::cents($item['cost_price']) / 100,
            'selling_price' => filled($item['selling_price'] ?? null) ? SupplierService::cents($item['selling_price']) / 100 : null,
            'serials' => $serials,
        ];
    }

    /**
     * Re-check, under the product locks, that no serial already exists — another GRN may have saved it
     * since the request was validated.
     *
     * @param  list<array{product: Product, serials: list<string>}>  $lines
     *
     * @throws ValidationException
     */
    private function ensureSerialsAreNew(array $lines): void
    {
        foreach ($lines as $line) {
            $existing = $line['product']->existingSerials($line['serials']);

            if ($existing !== []) {
                throw ValidationException::withMessages([
                    'items' => "Serial \"{$existing[0]}\" already exists for \"{$line['product']->name}\".",
                ]);
            }
        }
    }
}
