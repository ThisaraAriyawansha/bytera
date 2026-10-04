<?php

namespace Tests;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\StockService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Receive stock into a new batch the way a GRN does (counters and units included), with a chosen
     * receipt time so FIFO order can be set up.
     *
     * @param  list<string>  $serials
     */
    protected function receiveStock(
        Product $product,
        int $qty,
        float $cost,
        ?float $price = null,
        mixed $receivedAt = null,
        array $serials = [],
        string $location = 'stores',
    ): ProductBatch {
        return DB::transaction(function () use ($product, $qty, $cost, $price, $receivedAt, $serials, $location): ProductBatch {
            $stock = app(StockService::class);
            $batch = $stock->receiveBatch($stock->lockProduct($product->id), $location, $qty, $cost, $price, 'Test stock', $serials);
            $batch->update(['received_at' => $receivedAt ?? now()]);

            return $batch;
        });
    }
}
