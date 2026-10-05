<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private ShiftService $shifts) {}

    /**
     * Add Expense (`finance.addExpense`, SPEC §8.19) as a new EXP- record. When it was paid from the cash drawer,
     * the chosen shift must still be open and its `cash_expenses_total` goes up by the amount. Salaries can't
     * be added here — only the Salary module creates them.
     *
     * @param  array{category: string, amount: float|int|string, note?: ?string, shift_id?: ?int}  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $paidBy): Expense
    {
        if (! array_key_exists($data['category'], Expense::manualCategories())) {
            throw ValidationException::withMessages(['category' => 'Salaries are recorded from the Salary page.']);
        }

        $amountCents = SupplierService::cents($data['amount']);

        return DB::transaction(function () use ($data, $paidBy, $amountCents): Expense {
            $shift = empty($data['shift_id']) ? null : $this->shifts->lockOpenShift((int) $data['shift_id']);

            $expense = $this->record($data['category'], $amountCents, (string) ($data['note'] ?? ''), $paidBy, $shift);

            if ($shift !== null) {
                $this->shifts->recordCashExpense($shift, $amountCents / 100);
            }

            return $expense;
        });
    }

    /**
     * Write an expense row (inside the caller's transaction). Shared with the Salary module's linked expense.
     */
    public function record(string $category, int $amountCents, string $note, User $paidBy, ?Shift $shift, ?int $salaryPaymentId = null): Expense
    {
        return Expense::query()->create([
            'expense_no' => Numbering::next('expense', Numbering::PREFIXES['expense']),
            'category' => $category,
            'amount' => $amountCents / 100,
            'note' => trim($note),
            'paid_by_id' => $paidBy->id,
            'paid_by_name' => $paidBy->name ?: $paidBy->email,
            'linked_salary_payment_id' => $salaryPaymentId,
            'shift_id' => $shift?->id,
            'shift_no' => $shift?->shift_no,
        ]);
    }

    /**
     * Delete an expense (`finance.deleteExpense`). If it was paid from a shift that is still open, the amount goes
     * back into that drawer. A salary's expense is removed by deleting the salary payment instead.
     *
     * @throws ValidationException
     */
    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense): void {
            $expense = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($expense->linked_salary_payment_id !== null) {
                $paymentNo = $expense->linkedSalaryPayment?->payment_no ?? 'a salary payment';

                throw ValidationException::withMessages([
                    'expense' => "{$expense->expense_no} records {$paymentNo} — delete it from the Salary page instead.",
                ]);
            }

            $this->reverseShiftDebit($expense);

            $expense->delete();
        });
    }

    /**
     * Put a deleted expense's cash back into its shift while that shift is still open (inside a transaction).
     * Returns whether the shift was given the cash back.
     */
    public function reverseShiftDebit(Expense $expense): bool
    {
        if ($expense->shift_id === null) {
            return false;
        }

        $shift = Shift::query()->whereKey($expense->shift_id)->lockForUpdate()->first();

        return $shift !== null && $this->shifts->removeCashExpense($shift, $expense->amount);
    }
}
