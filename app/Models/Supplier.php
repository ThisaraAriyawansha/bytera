<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'phone',
    'email',
    'address',
    'total_payable',
    'amount_paid',
    'balance',
    'payment_status',
    'last_payment_at',
    'last_statement_sent_at',
])]
class Supplier extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_payable' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'last_payment_at' => 'datetime',
            'last_statement_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the payments made to the supplier.
     *
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * Get the GRNs received from the supplier.
     *
     * @return HasMany<Grn, $this>
     */
    public function grns(): HasMany
    {
        return $this->hasMany(Grn::class);
    }

    /**
     * Get the product batches supplied.
     *
     * @return HasMany<ProductBatch, $this>
     */
    public function productBatches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }
}
