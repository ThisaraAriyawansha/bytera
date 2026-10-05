<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Job;
use App\Models\SalaryPayment;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalaryService
{
    /**
     * The most sales and the most jobs one commission picker search returns.
     */
    public const SEARCH_LIMIT = 15;

    public function __construct(
        private ShiftService $shifts,
        private ExpenseService $expenses,
    ) {}

    /**
     * Everyone who can be paid a salary: all users except the Super Admin, by name.
     *
     * @return Collection<int, User>
     */
    public static function employees(): Collection
    {
        return User::query()
            ->where('role', '!=', Permissions::SUPER_ADMIN)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Employee Setup (`salary.manageConfig`): the type, monthly amount and commission % that pre-fill Issue Payment.
     * A null type clears the setup.
     *
     * @param  array{salary_type: ?string, salary_monthly_amount?: float|int|string|null, salary_commission_percent?: float|int|string|null}  $data
     *
     * @throws ValidationException
     */
    public function updateSetup(User $employee, array $data): User
    {
        $this->ensurePayee($employee, 'salary_type');

        $type = $data['salary_type'] ?? null;

        $employee->update([
            'salary_type' => $type,
            'salary_monthly_amount' => in_array($type, ['monthly', 'hybrid'], true) ? SupplierService::cents($data['salary_monthly_amount'] ?? 0) / 100 : null,
            'salary_commission_percent' => in_array($type, ['commission', 'hybrid'], true) ? round((float) ($data['salary_commission_percent'] ?? 0), 2) : null,
        ]);

        return $employee;
    }

    /**
     * Issue a salary payment (`salary.issue`, SPEC §8.20) in one transaction:
     *
     * - a SAL- payment with the type, amount, commission base / %, the linked sales and jobs, period and note;
     * - its automatic salaries Expense (next EXP- number, note "Salary payment SAL-x — Name (Period)"), linked both ways;
     * - every linked sale / job is marked with `commission_payment_id` — failing if any is already claimed or the sale was reversed;
     * - when paid from the cash drawer, the open shift's `cash_expenses_total` goes up by the amount.
     *
     * @param  array{
     *     user_id: int, type: string, amount: float|int|string, commission_base?: float|int|string|null,
     *     commission_percent?: float|int|string|null, period_label: string, note?: ?string, shift_id?: ?int,
     *     sale_ids?: list<int>, job_ids?: list<int>
     * }  $data
     *
     * @throws ValidationException
     */
    public function issue(array $data, User $issuer): SalaryPayment
    {
        $employee = User::query()->findOrFail($data['user_id']);
        $this->ensurePayee($employee, 'user_id');

        $amountCents = SupplierService::cents($data['amount']);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'The amount to pay must be greater than 0.']);
        }

        $hasCommission = $data['type'] !== 'monthly';

        return DB::transaction(function () use ($data, $issuer, $employee, $amountCents, $hasCommission): SalaryPayment {
            $shift = empty($data['shift_id']) ? null : $this->shifts->lockOpenShift((int) $data['shift_id']);

            [$sales, $jobs] = $hasCommission
                ? $this->lockUnclaimedItems($data['sale_ids'] ?? [], $data['job_ids'] ?? [])
                : [new Collection, new Collection];

            $items = [
                ...$sales->map(fn (Sale $sale): array => ['kind' => 'sale', 'id' => $sale->id, 'number' => $sale->invoice_no, 'amount' => (float) $sale->total_amount])->all(),
                ...$jobs->map(fn (Job $job): array => ['kind' => 'job', 'id' => $job->id, 'number' => $job->job_no, 'amount' => self::jobCommissionAmount($job)])->all(),
            ];

            $baseCents = ! $hasCommission ? null : (
                isset($data['commission_base']) && $data['commission_base'] !== ''
                    ? SupplierService::cents($data['commission_base'])
                    : array_sum(array_map(fn (array $item): int => SupplierService::cents($item['amount']), $items))
            );

            $paymentNo = Numbering::next('salary', Numbering::PREFIXES['salary']);
            $employeeName = $employee->name ?: $employee->email;
            $period = trim($data['period_label']);

            $expense = $this->expenses->record('salaries', $amountCents, "Salary payment {$paymentNo} — {$employeeName} ({$period})", $issuer, $shift);

            $payment = SalaryPayment::query()->create([
                'payment_no' => $paymentNo,
                'user_id' => $employee->id,
                'user_name' => $employeeName,
                'user_role' => $employee->role,
                'type' => $data['type'],
                'amount' => $amountCents / 100,
                'commission_base' => $baseCents === null ? null : $baseCents / 100,
                'commission_percent' => $hasCommission ? round((float) ($data['commission_percent'] ?? 0), 2) : null,
                'commission_items' => $items,
                'period_label' => $period,
                'note' => trim((string) ($data['note'] ?? '')),
                'issued_by_id' => $issuer->id,
                'issued_by_name' => $issuer->name ?: $issuer->email,
                'linked_expense_id' => $expense->id,
                'shift_id' => $shift?->id,
                'shift_no' => $shift?->shift_no,
            ]);

            $expense->update(['linked_salary_payment_id' => $payment->id]);

            if ($sales->isNotEmpty()) {
                Sale::query()->whereKey($sales->modelKeys())->update(['commission_payment_id' => $payment->id]);
            }

            if ($jobs->isNotEmpty()) {
                Job::query()->whereKey($jobs->modelKeys())->update(['commission_payment_id' => $payment->id]);
            }

            if ($shift !== null) {
                $this->shifts->recordCashExpense($shift, $amountCents / 100);
            }

            return $payment;
        });
    }

    /**
     * Delete a salary payment (`salary.delete`) in one transaction: its expense is removed, the linked sales and
     * jobs are freed for a later payment, and the cash goes back into its shift if that shift is still open.
     * Returns whether a shift's drawer was given the cash back.
     */
    public function delete(SalaryPayment $payment): bool
    {
        return DB::transaction(function () use ($payment): bool {
            $payment = SalaryPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $expense = Expense::query()->whereKey($payment->linked_expense_id)->lockForUpdate()->first();

            $shiftRestored = $expense !== null && $this->expenses->reverseShiftDebit($expense);

            Sale::query()->where('commission_payment_id', $payment->id)->update(['commission_payment_id' => null]);
            Job::query()->where('commission_payment_id', $payment->id)->update(['commission_payment_id' => null]);

            $payment->delete();
            $expense?->delete();

            return $shiftRestored;
        });
    }

    /**
     * The commission picker: unclaimed, non-cancelled sales and unclaimed jobs matching an invoice number, job
     * number ("123" also finds JOB-00123) or customer name. With no term, the latest of each.
     *
     * @return list<array{kind: string, id: int, number: string, amount: float, description: string}>
     */
    public function commissionCandidates(string $term): array
    {
        $term = trim($term);
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $sales = Sale::query()
            ->whereNull('status')
            ->whereNull('commission_payment_id')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('invoice_no', 'like', $pattern)
                ->orWhere('customer_name', 'like', $pattern)))
            ->latest()
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'invoice_no', 'customer_name', 'cashier_name', 'total_amount', 'created_at']);

        $jobs = Job::query()
            ->whereNull('commission_payment_id')
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('job_no', 'like', $pattern)
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('job_no', sprintf('JOB-%05d', (int) $term)))
                ->orWhere('customer_name', 'like', $pattern)))
            ->latest()
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'job_no', 'customer_name', 'assigned_technician_name', 'status', 'repair_cost', 'estimated_cost', 'created_at']);

        return [
            ...$sales->map(fn (Sale $sale): array => [
                'kind' => 'sale',
                'id' => $sale->id,
                'number' => $sale->invoice_no,
                'amount' => (float) $sale->total_amount,
                'description' => implode(' · ', array_filter([$sale->customer_name, $sale->cashier_name, $sale->created_at->format('M j, Y')])),
            ])->all(),
            ...$jobs->map(fn (Job $job): array => [
                'kind' => 'job',
                'id' => $job->id,
                'number' => $job->job_no,
                'amount' => self::jobCommissionAmount($job),
                'description' => implode(' · ', array_filter([$job->customer_name, $job->assigned_technician_name, $job->statusLabel(), $job->created_at->format('M j, Y')])),
            ])->all(),
        ];
    }

    /**
     * A job's commission value: its repair cost, or the estimate when no repair cost was set.
     */
    public static function jobCommissionAmount(Job $job): float
    {
        return (float) ($job->repair_cost ?? $job->estimated_cost);
    }

    /**
     * "How this amount was calculated": the commission (base × %), the monthly part, and any adjustment the issuer
     * typed over the worked-out amount, ending with the amount paid.
     *
     * @return list<array{label: string, amount: float}>
     */
    public static function calculationLines(SalaryPayment $payment): array
    {
        $amountCents = SupplierService::cents($payment->amount);

        if ($payment->type === 'monthly') {
            return [['label' => 'Monthly salary', 'amount' => $amountCents / 100]];
        }

        $percent = (float) $payment->commission_percent;
        $commissionCents = (int) round(SupplierService::cents($payment->commission_base) * $percent / 100);
        $percentLabel = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
        $lines = [[
            'label' => 'Commission: '.Money::format($payment->commission_base)." × {$percentLabel}%",
            'amount' => $commissionCents / 100,
        ]];

        $remainderCents = $amountCents - $commissionCents;

        if ($payment->type === 'hybrid') {
            $lines[] = ['label' => 'Monthly salary', 'amount' => $remainderCents / 100];
        } elseif ($remainderCents !== 0) {
            $lines[] = ['label' => 'Adjustment', 'amount' => $remainderCents / 100];
        }

        $lines[] = ['label' => 'Amount paid', 'amount' => $amountCents / 100];

        return $lines;
    }

    /**
     * Lock the chosen sales and jobs; every one must exist, be unclaimed and (for sales) not reversed.
     *
     * @param  list<int>  $saleIds
     * @param  list<int>  $jobIds
     * @return array{0: Collection<int, Sale>, 1: Collection<int, Job>}
     *
     * @throws ValidationException
     */
    private function lockUnclaimedItems(array $saleIds, array $jobIds): array
    {
        $saleIds = array_values(array_unique(array_map('intval', $saleIds)));
        $jobIds = array_values(array_unique(array_map('intval', $jobIds)));

        $sales = $saleIds === [] ? new Collection : Sale::query()->with('commissionPayment:id,payment_no')->whereKey($saleIds)->orderBy('id')->lockForUpdate()->get();
        $jobs = $jobIds === [] ? new Collection : Job::query()->with('commissionPayment:id,payment_no')->whereKey($jobIds)->orderBy('id')->lockForUpdate()->get();

        if ($sales->count() !== count($saleIds) || $jobs->count() !== count($jobIds)) {
            throw ValidationException::withMessages(['items' => 'One of the linked sales or jobs no longer exists. Search and link them again.']);
        }

        foreach ($sales as $sale) {
            if ($sale->status === 'cancelled') {
                throw ValidationException::withMessages(['items' => "{$sale->invoice_no} has been reversed and can't earn commission."]);
            }
        }

        foreach ([...$sales, ...$jobs] as $item) {
            if ($item->commission_payment_id !== null) {
                $number = $item instanceof Sale ? $item->invoice_no : $item->job_no;
                $paidIn = $item->commissionPayment?->payment_no ?? 'another salary payment';

                throw ValidationException::withMessages(['items' => "Commission on {$number} has already been paid in {$paidIn}."]);
            }
        }

        return [$sales, $jobs];
    }

    /**
     * The Super Admin is never a salary payee.
     *
     * @throws ValidationException
     */
    private function ensurePayee(User $employee, string $errorKey): void
    {
        if ($employee->role === Permissions::SUPER_ADMIN) {
            throw ValidationException::withMessages([$errorKey => 'The Super Admin is not paid a salary here.']);
        }
    }
}
