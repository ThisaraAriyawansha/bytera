<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'batch_id',
    'serial_number',
    'cost_price',
    'selling_price',
    'status',
    'location',
    'sale_id',
    'sold_at',
    'stock_out_id',
    'issued_at',
])]
class ProductUnit extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'sold_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * Get the unit's product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the batch the unit belongs to.
     *
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * Get the sale the unit was sold on.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the stock out the unit was issued on.
     *
     * @return BelongsTo<StockOut, $this>
     */
    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(StockOut::class);
    }
}
