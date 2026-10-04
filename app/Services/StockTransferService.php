<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    /**
     * Transfer fields an admin edit may change.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = ['note'];

    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {}

    /**
     * Move stock from Stores to Showroom in one transaction (SPEC §8.13). Each Stores batch consumed (FIFO,
     * or the batches of the picked serial units) gets a new Showroom batch at the same cost / selling price,
     * serial units move with it, the location counters shift and a `transfer` movement is written per item.
     *
     * @param  array{note?: ?string, items: list<array{product_id: int, qty?: ?int, unit_ids?: list<int>}>}  $data
     *
     * @throws ValidationException
     */
    public function transfer(array $data, User $transferredBy): StockTransfer
    {
        return DB::transaction(function () use ($data, $transferredBy): StockTransfer {
            $products = Product::query()
                ->whereKey(collect($data['items'])->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $transferNo = Numbering::next('transfer', Numbering::PREFIXES['transfer']);
            $note = "Transferred via {$transferNo}";

            $transfer = StockTransfer::query()->create([
                'transfer_no' => $transferNo,
                'transferred_by_id' => $transferredBy->id,
                'transferred_by_name' => $transferredBy->name ?: $transferredBy->email,
                'note' => (string) ($data['note'] ?? ''),
            ]);

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']];
                $unitIds = $product->track_serial ? array_values($item['unit_ids'] ?? []) : [];
                $qty = $product->track_serial ? count($unitIds) : (int) $item['qty'];

                $this->stock->moveStock($product, 'stores', 'showroom', $qty);

                $line = $product->track_serial
                    ? $this->moveUnits($product, $unitIds, $note)
                    : $this->moveBatches($product, $qty, $note);

                $transfer->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'qty' => $qty,
                    'serial_numbers' => $line['serial_numbers'],
                    'source_batch_ids' => $line['source_batch_ids'],
                    'new_batch_ids' => $line['new_batch_ids'],
                ]);

                $this->stock->recordMovement($product, 'transfer', $qty, 'transfer', $transfer->id, $note, $transferredBy, [
                    'from_location' => 'stores',
                    'to_location' => 'showroom',
                ]);
            }

            return $transfer;
        });
    }

    /**
     * Admin edit of a transfer (SPEC §8.13): the note only, audit logged in the same transaction.
     *
     * @param  array{note?: ?string}  $data
     * @return list<array{field: string, before: mixed, after: mixed}> the changes made
     */
    public function update(StockTransfer $transfer, array $data, User $editor): array
    {
        return DB::transaction(function () use ($transfer, $data, $editor): array {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            $patch = ['note' => (string) ($data['note'] ?? '')];

            $changes = $this->audit->diff($transfer->only(self::EDITABLE_FIELDS), $patch, self::EDITABLE_FIELDS);

            if ($changes === []) {
                return [];
            }

            $transfer->update($patch);

            $this->audit->write($transfer->getTable(), $transfer->id, $transfer->transfer_no, $changes, $editor);

            return $changes;
        });
    }

    /**
     * FIFO-consume Stores batches and open a matching Showroom batch for each one consumed.
     *
     * @return array{serial_numbers: list<string>, source_batch_ids: list<int>, new_batch_ids: list<int>}
     *
     * @throws ValidationException
     */
    private function moveBatches(Product $product, int $qty, string $note): array
    {
        $allocation = $this->stock->allocateFifo($product, 'stores', $qty);
        $this->stock->applyAllocation($allocation);

        $sources = ProductBatch::query()->findMany(array_column($allocation['consumed'], 'batchId'))->keyBy('id');
        $newBatchIds = [];

        foreach ($allocation['consumed'] as $consumed) {
            $newBatchIds[] = $this->stock->splitBatchTo($sources[$consumed['batchId']], 'showroom', $consumed['qty'], $note)->id;
        }

        return [
            'serial_numbers' => [],
            'source_batch_ids' => array_column($allocation['consumed'], 'batchId'),
            'new_batch_ids' => $newBatchIds,
        ];
    }

    /**
     * Move picked serial units to Showroom: each unit's Stores batch gives up the unit to a new Showroom batch,
     * and the unit follows it.
     *
     * @param  list<int>  $unitIds
     * @return array{serial_numbers: list<string>, source_batch_ids: list<int>, new_batch_ids: list<int>}
     *
     * @throws ValidationException
     */
    private function moveUnits(Product $product, array $unitIds, string $note): array
    {
        $units = $this->stock->lockAvailableUnits($product, 'stores', $unitIds);
        $sourceBatchIds = [];
        $newBatchIds = [];

        foreach ($this->stock->takeUnitsFromBatches($units) as ['batch' => $source, 'qty' => $qty]) {
            $newBatch = $this->stock->splitBatchTo($source, 'showroom', $qty, $note);

            $units->where('batch_id', $source->id)->toQuery()->update([
                'batch_id' => $newBatch->id,
                'location' => 'showroom',
            ]);

            $sourceBatchIds[] = $source->id;
            $newBatchIds[] = $newBatch->id;
        }

        return [
            'serial_numbers' => $units->pluck('serial_number')->all(),
            'source_batch_ids' => $sourceBatchIds,
            'new_batch_ids' => $newBatchIds,
        ];
    }
}
