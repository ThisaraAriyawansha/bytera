<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
     * Days before the end date when a warranty shows as "Expiring Soon".
     */
    public const EXPIRING_SOON_DAYS = 30;

    /**
     * The computed statuses (SPEC §8.10) and their labels / badge variants, in filter-tab order.
     *
     * @var array<string, array{label: string, variant: string}>
     */
    public const STATUSES = [
        'active' => ['label' => 'Active', 'variant' => 'success'],
        'expiring' => ['label' => 'Expiring Soon', 'variant' => 'warning'],
        'expired' => ['label' => 'Expired', 'variant' => 'danger'],
        'claimed' => ['label' => 'Claimed', 'variant' => 'default'],
    ];

    /**
     * Get the computed status: claimed → Claimed; ended before today → Expired; ≤ 30 days left →
     * "Expiring Soon · N days"; otherwise Active.
     *
     * @return array{key: string, label: string, variant: string, days_left: int}
     */
    public function computedStatus(): array
    {
        $daysLeft = (int) today()->diffInDays($this->end_date, false);

        $key = match (true) {
            $this->status === 'claimed' => 'claimed',
            $daysLeft < 0 => 'expired',
            $daysLeft <= self::EXPIRING_SOON_DAYS => 'expiring',
            default => 'active',
        };

        $label = self::STATUSES[$key]['label'];

        if ($key === 'expiring') {
            $label .= " · {$daysLeft} ".str('day')->plural($daysLeft);
        }

        return ['key' => $key, 'label' => $label, 'variant' => self::STATUSES[$key]['variant'], 'days_left' => $daysLeft];
    }

    /**
     * Determine whether the warranty can still be claimed (not claimed and not expired).
     */
    public function isClaimable(): bool
    {
        return in_array($this->computedStatus()['key'], ['active', 'expiring'], true);
    }

    /**
     * Scope the query to one computed status.
     *
     * @param  Builder<Warranty>  $query
     */
    #[Scope]
    protected function withComputedStatus(Builder $query, string $status): void
    {
        $today = today()->toDateString();
        $soon = today()->addDays(self::EXPIRING_SOON_DAYS)->toDateString();

        match ($status) {
            'claimed' => $query->where('status', 'claimed'),
            'expired' => $query->where('status', '!=', 'claimed')->where('end_date', '<', $today),
            'expiring' => $query->where('status', '!=', 'claimed')->whereBetween('end_date', [$today, $soon]),
            'active' => $query->where('status', '!=', 'claimed')->where('end_date', '>', $soon),
            default => null,
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
