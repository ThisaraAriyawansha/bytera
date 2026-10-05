<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'quotation_no',
    'customer_id',
    'customer_name',
    'customer_phone',
    'customer_address',
    'prepared_by_id',
    'prepared_by_name',
    'subtotal',
    'discount_amount',
    'total_amount',
    'valid_until',
    'status',
    'note',
])]
class Quotation extends Model
{
    /**
     * Quotation statuses and their labels / badge variants.
     *
     * @var array<string, array{label: string, variant: string}>
     */
    public const STATUSES = [
        'sent' => ['label' => 'Sent', 'variant' => 'info'],
        'accepted' => ['label' => 'Accepted', 'variant' => 'success'],
        'rejected' => ['label' => 'Rejected', 'variant' => 'danger'],
        'expired' => ['label' => 'Expired', 'variant' => 'warning'],
        'converted' => ['label' => 'Converted', 'variant' => 'default'],
    ];

    /**
     * The customer name stored and printed when none is typed.
     */
    public const WALK_IN_CUSTOMER = 'Walk-in Customer';

    /**
     * Get the status to show: a sent quotation past its valid-until date shows as expired.
     */
    public function displayStatus(): string
    {
        return $this->status === 'sent' && $this->valid_until->lt(today()) ? 'expired' : $this->status;
    }

    /**
     * Get the label of the status to show.
     */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->displayStatus()]['label'];
    }

    /**
     * Scope the query to quotations showing a status (a sent one past its date counts as expired).
     *
     * @param  Builder<Quotation>  $query
     */
    #[Scope]
    protected function withDisplayStatus(Builder $query, string $status): void
    {
        $today = today()->toDateString();

        match ($status) {
            'sent' => $query->where('status', 'sent')->where('valid_until', '>=', $today),
            'expired' => $query->where(fn (Builder $query) => $query
                ->where('status', 'expired')
                ->orWhere(fn (Builder $query) => $query->where('status', 'sent')->where('valid_until', '<', $today))),
            default => $query->where('status', $status),
        };
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'valid_until' => 'date',
        ];
    }

    /**
     * Get the quotation's line items.
     *
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * Get the quotation's customer.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who prepared the quotation.
     *
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }
}
