<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreProductBatchRequest;
use App\Http\Requests\Catalog\StoreProductUnitsRequest;
use App\Http\Requests\Catalog\UpdateProductBatchRequest;
use App\Http\Requests\Catalog\UpdateProductUnitRequest;
use App\Http\Resources\ProductBatchResource;
use App\Http\Resources\ProductUnitResource;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductStockController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * In-stock serial units of a product at one location, oldest first, for the serial pickers of
     * Stock Transfer, Stock Out and the POS.
     */
    public function availableUnits(Request $request, Product $product): JsonResponse
    {
        abort_unless(Gate::any(['stockTransfer.create', 'stockOut.create', 'sales.view']), 403);

        $location = $request->query('location');
        abort_unless(is_string($location) && array_key_exists($location, StockService::LOCATIONS), 422);

        $units = $product->units()
            ->where('status', 'in_stock')
            ->where('location', $location)
            ->orderBy('id')
            ->get(['id', 'batch_id', 'serial_number', 'cost_price', 'selling_price']);

        return response()->json([
            'units' => $units->map(fn (ProductUnit $unit): array => [
                'id' => $unit->id,
                'batch_id' => $unit->batch_id,
                'serial_number' => $unit->serial_number,
                'cost_price' => (float) $unit->cost_price,
                'selling_price' => $unit->selling_price === null ? null : (float) $unit->selling_price,
            ])->values(),
        ]);
    }

    /**
     * Active batches of a product at one location in FIFO order, for the POS batch picker. Each has its cost and
     * the price it sells at (the batch price, or the product price when the batch has none).
     */
    public function availableBatches(Request $request, Product $product): JsonResponse
    {
        abort_unless(Gate::allows('sales.view'), 403);

        $location = $request->query('location');
        abort_unless(is_string($location) && array_key_exists($location, StockService::LOCATIONS), 422);

        $batches = $product->batches()
            ->where('location', $location)
            ->where('status', 'active')
            ->where('remaining_qty', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get(['id', 'cost_price', 'selling_price', 'remaining_qty', 'received_at']);

        return response()->json([
            'batches' => $batches->map(fn (ProductBatch $batch): array => [
                'id' => $batch->id,
                'cost_price' => (float) $batch->cost_price,
                'selling_price' => (float) ($batch->selling_price ?? $product->selling_price),
                'remaining_qty' => $batch->remaining_qty,
                'received_at' => $batch->received_at->toDateString(),
            ])->values(),
        ]);
    }

    /**
     * List a product's stock batches, newest first, for the Stock Batches modal.
     */
    public function batches(Product $product): JsonResponse
    {
        $batches = $product->batches()
            ->when($product->track_serial, fn (Builder $query) => $query->withCount(['units' => fn (Builder $query) => $query->where('status', 'in_stock')]))
            ->latest('received_at')
            ->latest('id')
            ->get();

        return response()->json([
            'product' => $this->summary($product),
            'batches' => ProductBatchResource::collection($batches),
        ]);
    }

    /**
     * Add a batch of stock, which always lands in Stores.
     */
    public function storeBatch(StoreProductBatchRequest $request, Product $product): JsonResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product = $this->stock->lockProduct($product->id);
            $qty = $request->quantity();

            $batch = $this->stock->receiveBatch(
                $product,
                'stores',
                $qty,
                $request->validated('cost_price'),
                $request->validated('selling_price'),
                (string) $request->validated('note'),
                $product->track_serial ? $request->validated('serials') : [],
            );

            $this->stock->recordMovement($product, 'in', $qty, 'batch_edit', $batch->id, 'Stock batch added', $request->user(), [
                'location' => 'stores',
            ]);
        });

        return response()->json(['message' => 'Batch added to Stores.'], 201);
    }

    /**
     * Edit a batch's prices, quantities and note. A remaining-quantity change corrects the product's
     * counters at the batch's location and is written as an adjustment movement.
     */
    public function updateBatch(UpdateProductBatchRequest $request, Product $product, ProductBatch $batch): JsonResponse
    {
        DB::transaction(function () use ($request, $product, $batch): void {
            $product = $this->stock->lockProduct($product->id);
            $batch = ProductBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $validated = $request->validated();

            $delta = (int) $validated['remaining_qty'] - $batch->remaining_qty;

            $this->stock->adjustStock($product, $batch->location, $delta);

            $batch->update([
                'cost_price' => $validated['cost_price'],
                'selling_price' => $validated['selling_price'],
                'total_qty' => (int) $validated['total_qty'],
                'remaining_qty' => (int) $validated['remaining_qty'],
                'status' => $validated['remaining_qty'] > 0 ? 'active' : 'depleted',
                'note' => (string) $validated['note'],
            ]);

            if ($product->track_serial) {
                $batch->units()->where('status', 'in_stock')->update([
                    'cost_price' => $batch->cost_price,
                    'selling_price' => $batch->selling_price,
                ]);
            }

            if ($delta !== 0) {
                $this->stock->recordMovement($product, 'adjustment', $delta, 'batch_edit', $batch->id, 'Batch quantity corrected', $request->user(), [
                    'location' => $batch->location,
                ]);
            }
        });

        return response()->json(['message' => 'Batch updated.']);
    }

    /**
     * List the serial-tracked units of a batch for the Serial Numbers modal.
     */
    public function units(Product $product, ProductBatch $batch): JsonResponse
    {
        return response()->json([
            'product' => $this->summary($product),
            'batch' => ProductBatchResource::make($batch),
            'units' => ProductUnitResource::collection($batch->units()->orderBy('id')->get()),
        ]);
    }

    /**
     * Add more serial-tracked units to a batch, raising the batch and product counters at its location.
     */
    public function storeUnits(StoreProductUnitsRequest $request, Product $product, ProductBatch $batch): JsonResponse
    {
        if (! $product->track_serial) {
            throw ValidationException::withMessages(['serials' => 'This product does not track serial numbers.']);
        }

        $serials = $request->validated('serials');

        DB::transaction(function () use ($request, $product, $batch, $serials): void {
            $product = $this->stock->lockProduct($product->id);
            $batch = ProductBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $qty = count($serials);

            $this->stock->createUnits($product, $batch, $serials);
            $batch->update([
                'total_qty' => $batch->total_qty + $qty,
                'remaining_qty' => $batch->remaining_qty + $qty,
                'status' => 'active',
            ]);
            $this->stock->adjustStock($product, $batch->location, $qty);

            $this->stock->recordMovement($product, 'in', $qty, 'batch_edit', $batch->id, 'Serial numbers added', $request->user(), [
                'location' => $batch->location,
            ]);
        });

        return response()->json(['message' => count($serials).' '.str('serial number')->plural(count($serials)).' added.'], 201);
    }

    /**
     * Correct a unit's serial number.
     */
    public function updateUnit(UpdateProductUnitRequest $request, Product $product, ProductUnit $unit): JsonResponse
    {
        $unit->update(['serial_number' => $request->validated('serial_number')]);

        return response()->json(['message' => 'Serial number updated.']);
    }

    /**
     * Remove an in-stock unit, lowering its batch and the product counters at its location.
     */
    public function destroyUnit(Request $request, Product $product, ProductUnit $unit): JsonResponse
    {
        Gate::authorize('products.unit.delete');

        DB::transaction(function () use ($request, $product, $unit): void {
            $unit = ProductUnit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();

            if ($unit->status !== 'in_stock') {
                throw ValidationException::withMessages([
                    'unit' => $unit->status === 'sold'
                        ? 'Cannot remove a unit that has already been sold'
                        : 'Cannot remove a unit that has already been issued',
                ]);
            }

            $product = $this->stock->lockProduct($product->id);
            $batch = ProductBatch::query()->whereKey($unit->batch_id)->lockForUpdate()->firstOrFail();

            $batch->update([
                'total_qty' => max(0, $batch->total_qty - 1),
                'remaining_qty' => max(0, $batch->remaining_qty - 1),
                'status' => $batch->remaining_qty - 1 > 0 ? 'active' : 'depleted',
            ]);
            $this->stock->adjustStock($product, $unit->location, -1);

            $this->stock->recordMovement($product, 'adjustment', -1, 'batch_edit', $batch->id, "Serial {$unit->serial_number} removed", $request->user(), [
                'location' => $unit->location,
            ]);

            $unit->delete();
        });

        return response()->json(['message' => "Serial \"{$unit->serial_number}\" removed."]);
    }

    /**
     * Get the product figures shown in the stock modals' headers.
     *
     * @return array{name: string, sku: string, track_serial: bool, selling_price: float, total_stock: int, stores_stock: int, showroom_stock: int}
     */
    private function summary(Product $product): array
    {
        $product->refresh();

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'track_serial' => $product->track_serial,
            'selling_price' => (float) $product->selling_price,
            'total_stock' => $product->total_stock,
            'stores_stock' => $product->stores_stock,
            'showroom_stock' => $product->showroom_stock,
        ];
    }
}
