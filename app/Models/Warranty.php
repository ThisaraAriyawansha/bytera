<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'customer_name',
    'product_id',
    'product_name',
    'sale_id',
    'serial_number',
    'warranty_months',
    'start_date',
    'end_date',
    'status',
    'claim_note',
    'claimed_at',
])]
class Warranty extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warranty_months' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * Get the warranty's customer.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the covered product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the sale that issued the warranty.
     *
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
