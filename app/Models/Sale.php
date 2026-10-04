<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_no',
    'customer_id',
    'customer_name',
    'customer_phone',
    'customer_email',
    'cashier_id',
    'cashier_name',
    'job_id',
    'job_no',
    'services',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'payment_method',
    'payments',
    'kokopay_charge_percent',
    'kokopay_charge_amount',
    'card_charge_percent',
    'card_charge_amount',
    'payment_status',
    'amount_tendered',
    'change_amount',
    'points_redeemed',
    'note',
    'shift_id',
    'shift_no',
    'commission_payment_id',
    'status',
    'cancelled_at',
    'cancelled_by_id',
    'cancelled_by_name',
    'cancel_reason',
])]
class Sale extends Model
{
    /**
     * Payment methods offered at the POS and their labels.
     *
     * @var array<string, string>
     */
    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'card' => 'Card',
        'transfer' => 'Transfer',
        'kokopay' => 'KokoPay',
    ];

    /**
     * Get the payment legs: the split payments, or the whole total on the one method.
     *
     * @return list<array{method: string, label: string, amount: float}>
     */
    public function paymentLegs(): array
    {
        $legs = $this->payments ?: [['method' => $this->payment_method, 'amount' => $this->total_amount]];

        return array_map(fn (array $leg): array => [
            'method' => $leg['method'],
            'label' => self::PAYMENT_METHODS[$leg['method']] ?? $leg['method'],
            'amount' => (float) $leg['amount'],
        ], $legs);
    }

    /**
     * Get the payment label for lists: "Cash", or "Cash + Card" for a split.
     */
    public function paymentLabel(): string
    {
        return implode(' + ', array_column($this->paymentLegs(), 'label'));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'services' => 'array',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'payments' => 'array',
            'kokopay_charge_percent' => 'decimal:2',
            'kokopay_charge_amount' => 'decimal:2',
            'card_charge_percent' => 'decimal:2',
            'card_charge_amount' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'points_redeemed' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the sale's line items.
     *
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the warranties issued on the sale.
     *
     * @return HasMany<Warranty, $this>
     */
    public function warranties(): HasMany
    {
        return $this->hasMany(Warranty::class);
    }

    /**
     * Get the serial-tracked units sold on the sale.
     *
     * @return HasMany<ProductUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Get the sale's customer.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the cashier who made the sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Get the repair job billed on the sale.
     *
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the shift the sale was made in.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the salary payment that paid commission on the sale.
     *
     * @return BelongsTo<SalaryPayment, $this>
     */
    public function commissionPayment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'commission_payment_id');
    }

    /**
     * Get the user who cancelled the sale.
     *
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }
}
