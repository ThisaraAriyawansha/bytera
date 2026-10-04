<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifo_takes_the_oldest_batches_at_the_location_first(): void
    {
        $product = Product::factory()->create(['name' => 'SSD 512GB']);
        $newer = $this->batch($product, 'showroom', 5, 120, now()->subDay());
        $older = $this->batch($product, 'showroom', 2, 100, now()->subDays(3));
        $this->batch($product, 'stores', 50, 1, now()->subDays(9));
        $this->batch($product, 'showroom', 0, 1, now()->subDays(9), 'depleted');

        $allocation = DB::transaction(fn () => app(StockService::class)->allocateFifo($product, 'showroom', 3));

        $this->assertSame([
            ['batchId' => $older->id, 'qty' => 2, 'remainingAfter' => 0, 'costPrice' => 100.0],
            ['batchId' => $newer->id, 'qty' => 1, 'remainingAfter' => 4, 'costPrice' => 120.0],
        ], $allocation['consumed']);
        $this->assertSame(106.67, $allocation['costPrice']);
        $this->assertSame(2, $older->fresh()->remaining_qty, 'Allocating alone must not write.');

        DB::transaction(fn () => app(StockService::class)->applyAllocation($allocation));

        $this->assertSame(0, $older->fresh()->remaining_qty);
        $this->assertSame('depleted', $older->fresh()->status);
        $this->assertSame(4, $newer->fresh()->remaining_qty);
        $this->assertSame('active', $newer->fresh()->status);
    }

    public function test_a_preferred_batch_is_consumed_first(): void
    {
        $product = Product::factory()->create();
        $older = $this->batch($product, 'showroom', 5, 100, now()->subDays(3));
        $picked = $this->batch($product, 'showroom', 1, 150, now()->subDay());

        $allocation = DB::transaction(fn () => app(StockService::class)->allocateFifo($product->id, 'showroom', 2, $picked->id));

        $this->assertSame([$picked->id, $older->id], array_column($allocation['consumed'], 'batchId'));
        $this->assertSame(125.0, $allocation['costPrice']);
    }

    public function test_allocating_more_than_is_available_throws_the_spec_message(): void
    {
        $product = Product::factory()->create(['name' => 'HP 85A Toner']);
        $this->batch($product, 'showroom', 1, 100, now());
        $this->batch($product, 'stores', 9, 100, now());

        try {
            DB::transaction(fn () => app(StockService::class)->allocateFifo($product, 'showroom', 3));
            $this->fail('Expected the allocation to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame('Not enough stock for "HP 85A Toner": requested 3, only 1 available.', $exception->getMessage());
        }
    }

    public function test_adjusting_stock_keeps_the_total_in_sync_and_restocking_resets_the_alert(): void
    {
        $product = Product::factory()->create(['stores_stock' => 2, 'showroom_stock' => 1, 'total_stock' => 3, 'low_stock_alerted' => true]);
        $stock = app(StockService::class);

        DB::transaction(function () use ($stock, $product): void {
            $locked = $stock->lockProduct($product->id);
            $stock->adjustStock($locked, 'showroom', -1);
            $this->assertTrue($locked->low_stock_alerted, 'Selling must not reset the alert.');

            $stock->adjustStock($locked, 'stores', 4);
            $stock->moveStock($locked, 'stores', 'showroom', 3);
        });

        $product->refresh();
        $this->assertSame([3, 3, 6], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertFalse($product->low_stock_alerted);

        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $stock->adjustStock($stock->lockProduct($product->id), 'showroom', -4));
    }

    private function batch(Product $product, string $location, int $remaining, float $cost, mixed $receivedAt, string $status = 'active'): ProductBatch
    {
        return $product->batches()->create([
            'cost_price' => $cost,
            'selling_price' => null,
            'total_qty' => max($remaining, 1),
            'remaining_qty' => $remaining,
            'status' => $status,
            'location' => $location,
            'note' => '',
            'received_at' => $receivedAt,
        ]);
    }
}
