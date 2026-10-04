<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'grn_id',
    'product_id',
    'product_name',
    'sku',
    'qty',
    'cost_price',
    'selling_price',
    'serials',
    'batch_id',
])]
class GrnItem extends Model
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
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'serials' => 'array',
        ];
    }

    /**
     * Get the parent GRN.
     *
     * @return BelongsTo<Grn, $this>
     */
    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    /**
     * Get the received product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the batch created by the receipt.
     *
     * @return BelongsTo<ProductBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
