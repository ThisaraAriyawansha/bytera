<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_no',
    'user_id',
    'user_name',
    'user_role',
    'type',
    'amount',
    'commission_base',
    'commission_percent',
    'commission_items',
    'period_label',
    'note',
    'issued_by_id',
    'issued_by_name',
    'linked_expense_id',
    'shift_id',
    'shift_no',
    'email_sent_at',
])]
class SalaryPayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_base' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'commission_items' => 'array',
            'email_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the employee who was paid.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who issued the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /**
     * Get the expense that records the payment.
     *
     * @return BelongsTo<Expense, $this>
     */
    public function linkedExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'linked_expense_id');
    }

    /**
     * Get the shift the payment was made in.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the sales whose commission this payment covered.
     *
     * @return HasMany<Sale, $this>
     */
    public function commissionSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'commission_payment_id');
    }

    /**
     * Get the jobs whose commission this payment covered.
     *
     * @return HasMany<Job, $this>
     */
    public function commissionJobs(): HasMany
    {
        return $this->hasMany(Job::class, 'commission_payment_id');
    }
}
