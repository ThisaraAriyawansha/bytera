<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'stock_out_id',
    'product_id',
    'product_name',
    'sku',
    'qty',
    'serial_numbers',
    'cost_price',
])]
class StockOutItem extends Model
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
            'cost_price' => 'decimal:2',
        ];
    }

    /**
     * Get the parent stock out.
     *
     * @return BelongsTo<StockOut, $this>
     */
    public function stockOut(): BelongsTo
    {
        return $this->belongsTo(StockOut::class);
    }

    /**
     * Get the issued product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
