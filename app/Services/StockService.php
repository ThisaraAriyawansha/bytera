<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

class StockService
{
    /**
     * The two stock locations (SPEC §7): new stock lands in Stores, POS sells from Showroom.
     *
     * @var array<string, string>
     */
    public const LOCATIONS = [
        'stores' => 'Stores',
        'showroom' => 'Showroom',
    ];

    /**
     * Plan a FIFO allocation of `$qty` units from the product's active batches at one location (SPEC §7).
     *
     * Batches are locked FOR UPDATE and taken oldest `received_at` first; a preferred batch the cashier
     * picked goes first. Nothing is written — pass the result to applyAllocation() to consume it.
     * Must be called inside DB::transaction().
     *
     * @return array{consumed: list<array{batchId: int, qty: int, remainingAfter: int, costPrice: float}>, costPrice: float}
     *
     * @throws LogicException
     * @throws ValidationException
     */
    public function allocateFifo(Product|int $product, string $location, int $qty, ?int $preferredBatchId = null): array
    {
        $this->ensureTransaction();
        $this->ensureLocation($location);

        if ($qty < 1) {
            throw new InvalidArgumentException('The quantity to allocate must be at least 1.');
        }

        $productId = $product instanceof Product ? $product->id : $product;

        $batches = ProductBatch::query()
            ->where('product_id', $productId)
            ->where('location', $location)
            ->where('status', 'active')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($preferredBatchId !== null) {
            [$preferred, $others] = $batches->partition(fn (ProductBatch $batch): bool => $batch->id === $preferredBatchId);
            $batches = $preferred->concat($others);
        }

        $need = $qty;
        $cost = 0.0;
        $consumed = [];

        foreach ($batches as $batch) {
            if ($need === 0) {
                break;
            }

            $take = min($need, $batch->remaining_qty);

            if ($take < 1) {
                continue;
            }

            $need -= $take;
            $cost += $take * (float) $batch->cost_price;
            $consumed[] = [
                'batchId' => $batch->id,
                'qty' => $take,
                'remainingAfter' => $batch->remaining_qty - $take,
                'costPrice' => (float) $batch->cost_price,
            ];
        }

        if ($need > 0) {
            $name = $product instanceof Product ? $product->name : Product::query()->whereKey($productId)->value('name');
            $available = $qty - $need;

            throw ValidationException::withMessages([
                'stock' => "Not enough stock for \"{$name}\": requested {$qty}, only {$available} available.",
            ]);
        }

        return [
            'consumed' => $consumed,
            'costPrice' => round($cost / $qty, 2),
        ];
    }

    /**
     * Write a FIFO allocation to its (already locked) batches: lower `remaining_qty` and mark emptied batches depleted.
     *
     * @param  array{consumed: list<array{batchId: int, qty: int, remainingAfter: int, costPrice: float}>, costPrice: float}  $allocation
     */
    public function applyAllocation(array $allocation): void
    {
        $this->ensureTransaction();

        foreach ($allocation['consumed'] as $line) {
            ProductBatch::query()->whereKey($line['batchId'])->update([
                'remaining_qty' => $line['remainingAfter'],
                'status' => $line['remainingAfter'] > 0 ? 'active' : 'depleted',
            ]);
        }
    }

    /**
     * Lock a product row FOR UPDATE so its stock counters can be changed safely.
     *
     * @throws LogicException
     */
    public function lockProduct(int $productId): Product
    {
        $this->ensureTransaction();

        return Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
    }

    /**
     * Add (positive) or remove (negative) stock at one location, keeping `total_stock` equal to
     * `stores_stock + showroom_stock`. Any restock resets `low_stock_alerted`. The product must be locked.
     *
     * @throws ValidationException
     */
    public function adjustStock(Product $product, string $location, int $delta): void
    {
        $this->ensureTransaction();
        $this->ensureLocation($location);

        if ($delta === 0) {
            return;
        }

        $column = "{$location}_stock";
        $newLocationStock = $product->{$column} + $delta;

        if ($newLocationStock < 0) {
            $label = self::LOCATIONS[$location];

            throw ValidationException::withMessages([
                'stock' => "Not enough {$label} stock for \"{$product->name}\": requested ".abs($delta).", only {$product->{$column}} in {$label}.",
            ]);
        }

        $product->{$column} = $newLocationStock;
        $this->syncTotal($product);

        if ($delta > 0) {
            $product->low_stock_alerted = false;
        }

        $product->save();
    }

    /**
     * Move stock between locations; the total stays the same. The product must be locked.
     *
     * @throws ValidationException
     */
    public function moveStock(Product $product, string $from, string $to, int $qty): void
    {
        $this->ensureTransaction();
        $this->ensureLocation($from);
        $this->ensureLocation($to);

        $fromColumn = "{$from}_stock";

        if ($qty < 1 || $product->{$fromColumn} < $qty) {
            $label = self::LOCATIONS[$from];

            throw ValidationException::withMessages([
                'stock' => "Not enough {$label} stock for \"{$product->name}\": requested {$qty}, only {$product->{$fromColumn}} in {$label}.",
            ]);
        }

        $product->{$fromColumn} -= $qty;
        $product->{"{$to}_stock"} += $qty;
        $this->syncTotal($product);
        $product->save();
    }

    /**
     * Receive new stock as a batch at a location: create the batch (and one unit per serial for
     * serial-tracked products) and raise the product counters. The product must be locked.
     * The caller records the stock movement with its own reference.
     *
     * @param  list<string>  $serials
     *
     * @throws ValidationException
     */
    public function receiveBatch(
        Product $product,
        string $location,
        int $qty,
        float|string $costPrice,
        float|string|null $sellingPrice,
        string $note,
        array $serials = [],
        ?int $supplierId = null,
        ?int $sourceBatchId = null,
    ): ProductBatch {
        $this->ensureTransaction();
        $this->ensureLocation($location);

        if ($product->track_serial && count($serials) !== $qty) {
            throw new InvalidArgumentException('A serial-tracked batch needs exactly one serial number per unit.');
        }

        $batch = $product->batches()->create([
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'total_qty' => $qty,
            'remaining_qty' => $qty,
            'status' => $qty > 0 ? 'active' : 'depleted',
            'location' => $location,
            'source_batch_id' => $sourceBatchId,
            'supplier_id' => $supplierId,
            'note' => $note,
            'received_at' => now(),
        ]);

        $this->createUnits($product, $batch, $serials);
        $this->adjustStock($product, $location, $qty);

        return $batch;
    }

    /**
     * Create in-stock units for serial numbers in a batch, at the batch's location and prices.
     *
     * @param  list<string>  $serials
     */
    public function createUnits(Product $product, ProductBatch $batch, array $serials): void
    {
        foreach ($serials as $serial) {
            $product->units()->create([
                'batch_id' => $batch->id,
                'serial_number' => $serial,
                'cost_price' => $batch->cost_price,
                'selling_price' => $batch->selling_price,
                'status' => 'in_stock',
                'location' => $batch->location,
            ]);
        }
    }

    /**
     * Write a stock_movements row. `$qty` is signed: positive for stock in, negative for stock out.
     *
     * @param  'in'|'out'|'adjustment'|'transfer'  $type
     * @param  'grn'|'transfer'|'stock_out'|'sale'|'sale_cancel'|'batch_edit'  $referenceType
     * @param  array{location?: ?string, from_location?: ?string, to_location?: ?string, recipient?: ?string, reason?: ?string, reason_detail?: ?string, job_id?: ?int, job_no?: ?string, supplier_name?: ?string}  $details
     */
    public function recordMovement(
        Product|int $product,
        string $type,
        int $qty,
        string $referenceType,
        int $referenceId,
        string $note,
        User $performedBy,
        array $details = [],
    ): StockMovement {
        return StockMovement::query()->create([
            ...array_intersect_key($details, array_flip([
                'location', 'from_location', 'to_location', 'recipient', 'reason',
                'reason_detail', 'job_id', 'job_no', 'supplier_name',
            ])),
            'product_id' => $product instanceof Product ? $product->id : $product,
            'type' => $type,
            'qty' => $qty,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'note' => $note,
            'performed_by' => $performedBy->id,
            'performed_by_name' => $performedBy->name ?: $performedBy->email,
        ]);
    }

    /**
     * Recompute `total_stock` from the two location counters.
     */
    private function syncTotal(Product $product): void
    {
        $product->total_stock = $product->stores_stock + $product->showroom_stock;
    }

    /**
     * @throws LogicException
     */
    private function ensureTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Stock changes must run inside DB::transaction().');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function ensureLocation(string $location): void
    {
        if (! array_key_exists($location, self::LOCATIONS)) {
            throw new InvalidArgumentException("Unknown stock location [{$location}].");
        }
    }
}
