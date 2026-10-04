<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_transfer_id',
    'product_id',
    'product_name',
    'sku',
    'qty',
    'serial_numbers',
    'source_batch_ids',
    'new_batch_ids',
])]
class StockTransferItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'serial_numbers' => 'array',
            'source_batch_ids' => 'array',
            'new_batch_ids' => 'array',
        ];
    }

    /**
     * Get the parent stock transfer.
     *
     * @return BelongsTo<StockTransfer, $this>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    /**
     * Get the transferred product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
