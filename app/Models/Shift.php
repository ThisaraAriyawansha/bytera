<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shift_no',
    'cashier_id',
    'cashier_name',
    'status',
    'opening_float',
    'opened_at',
    'open_note',
    'cash_sales_total',
    'card_sales_total',
    'transfer_sales_total',
    'kokopay_sales_total',
    'sales_count',
    'cash_expenses_total',
    'closed_at',
    'expected_cash',
    'counted_cash',
    'variance',
    'close_note',
    'force_closed',
    'closed_by_id',
    'closed_by_name',
    'review_status',
    'reviewed_at',
    'reviewed_by_id',
    'reviewed_by_name',
    'review_note',
])]
class Shift extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_float' => 'decimal:2',
            'opened_at' => 'datetime',
            'cash_sales_total' => 'decimal:2',
            'card_sales_total' => 'decimal:2',
            'transfer_sales_total' => 'decimal:2',
            'kokopay_sales_total' => 'decimal:2',
            'sales_count' => 'integer',
            'cash_expenses_total' => 'decimal:2',
            'closed_at' => 'datetime',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'force_closed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the sales made during the shift.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Get the expenses paid during the shift.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get the cashier who opened the shift.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Get the user who closed the shift.
     *
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    /**
     * Get the user who reviewed the shift.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
