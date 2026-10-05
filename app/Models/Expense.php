<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'expense_no',
    'category',
    'amount',
    'note',
    'paid_by_id',
    'paid_by_name',
    'linked_salary_payment_id',
    'shift_id',
    'shift_no',
])]
class Expense extends Model
{
    /**
     * Expense categories and their labels (SPEC §5).
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'salaries' => 'Salaries',
        'maintenance' => 'Maintenance',
        'marketing' => 'Marketing',
        'other' => 'Other',
    ];

    /**
     * Categories Add Expense offers. Salaries are only ever created by the Salary module (SPEC §8.19).
     *
     * @return array<string, string>
     */
    public static function manualCategories(): array
    {
        return array_diff_key(self::CATEGORIES, ['salaries' => true]);
    }

    /**
     * Get the category label, e.g. "Utilities".
     */
    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user who paid the expense.
     *
     * @return BelongsTo<User, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }

    /**
     * Get the salary payment this expense records.
     *
     * @return BelongsTo<SalaryPayment, $this>
     */
    public function linkedSalaryPayment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'linked_salary_payment_id');
    }

    /**
     * Get the shift the expense was paid in.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
