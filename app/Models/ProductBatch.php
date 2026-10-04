<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_id',
    'cost_price',
    'selling_price',
    'total_qty',
    'remaining_qty',
    'status',
    'location',
    'source_batch_id',
    'supplier_id',
    'note',
    'received_at',
])]
class ProductBatch extends Model
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
            'total_qty' => 'integer',
            'remaining_qty' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Get the batch's product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the batch's supplier.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the batch this one was transferred from.
     *
     * @return BelongsTo<ProductBatch, $this>
     */
    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'source_batch_id');
    }

    /**
     * Get the serial-tracked units held in the batch.
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'batch_id');
    }
}
