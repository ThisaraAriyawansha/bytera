<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * Label and badge variant for each payment status (SPEC §8.18).
     *
     * @var array<string, array{label: string, variant: string}>
     */
    public const STATUSES = [
        'paid' => ['label' => 'Paid', 'variant' => 'success'],
        'partial' => ['label' => 'Partial', 'variant' => 'warning'],
        'outstanding' => ['label' => 'Outstanding', 'variant' => 'danger'],
    ];

    /**
     * Scope the query to suppliers whose name or phone contains the term.
     *
     * @param  Builder<Supplier>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $query->where(fn (Builder $query) => $query
            ->where('name', 'like', $pattern)
            ->orWhere('phone', 'like', $pattern));
    }

    /**
     * Scope the query to suppliers we still owe money.
     *
     * @param  Builder<Supplier>  $query
     */
    #[Scope]
    protected function owing(Builder $query): void
    {
        $query->where('balance', '>', 0);
    }

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
     * Count the days the balance has gone unpaid: since the last payment, or since the first GRN if never paid.
     * Load `withMin('grns', 'created_at')` first to avoid a query per supplier.
     */
    public function unpaidForDays(): int
    {
        $firstGrnAt = $this->grns_min_created_at ?? $this->grns()->min('created_at');
        $since = $this->last_payment_at ?? ($firstGrnAt !== null ? Carbon::parse($firstGrnAt) : $this->created_at);

        return (int) max(0, floor($since->diffInDays(now())));
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
