<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class ShiftService
{
    /**
     * Payment methods and the shift total each one adds to.
     *
     * @var array<string, string>
     */
    public const SALES_TOTAL_COLUMNS = [
        'cash' => 'cash_sales_total',
        'card' => 'card_sales_total',
        'transfer' => 'transfer_sales_total',
        'kokopay' => 'kokopay_sales_total',
    ];

    /**
     * Get the cashier's open shift, if any.
     */
    public function openShiftFor(User $cashier): ?Shift
    {
        return Shift::query()
            ->where('cashier_id', $cashier->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Open a shift for the cashier with an opening cash float (SPEC §8.4). A cashier can have only one open shift.
     *
     * @throws ValidationException
     */
    public function open(User $cashier, float|int|string $openingFloat, ?string $note = null): Shift
    {
        $floatCents = SupplierService::cents($openingFloat);

        if ($floatCents < 0) {
            throw ValidationException::withMessages(['opening_float' => "The opening float can't be negative."]);
        }

        return DB::transaction(function () use ($cashier, $floatCents, $note): Shift {
            // Serialises concurrent "open" clicks by the same cashier.
            User::query()->whereKey($cashier->id)->lockForUpdate()->first();

            if (Shift::query()->where('cashier_id', $cashier->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages([
                    'shift' => 'You already have an open shift — close it before opening a new one.',
                ]);
            }

            return Shift::query()->create([
                'shift_no' => Numbering::next('shift', Numbering::PREFIXES['shift']),
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name ?: $cashier->email,
                'status' => 'open',
                'opening_float' => $floatCents / 100,
                'opened_at' => now(),
                'open_note' => trim((string) $note),
                'cash_sales_total' => 0,
                'card_sales_total' => 0,
                'transfer_sales_total' => 0,
                'kokopay_sales_total' => 0,
                'sales_count' => 0,
                'cash_expenses_total' => 0,
                'close_note' => '',
                'review_note' => '',
            ]);
        });
    }

    /**
     * Close the cashier's own shift (SPEC §8.4): expected = float + cash sales − cash expenses,
     * variance = counted − expected, and the shift waits for a manager's review.
     *
     * @throws ValidationException
     */
    public function close(Shift $shift, User $cashier, float|int|string $countedCash, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($shift, $cashier, $countedCash, $note): Shift {
            $shift = $this->lockShift($shift->id);

            if ($shift->cashier_id !== $cashier->id) {
                throw ValidationException::withMessages(['shift' => 'Only the cashier who opened this shift can close it.']);
            }

            return $this->settle($shift, $cashier, $countedCash, $note, forceClosed: false);
        });
    }

    /**
     * Force Close (Admin Override, SPEC §8.19): an Admin / Super Admin closes anyone's open shift with the counted
     * cash, using the same Expected / Variance maths, marked `force_closed` with who closed it.
     *
     * @throws ValidationException
     */
    public function forceClose(Shift $shift, User $admin, float|int|string $countedCash, ?string $note = null): Shift
    {
        if (! Permissions::isAdmin($admin)) {
            throw new AuthorizationException('Only an Admin or Super Admin can force close a shift.');
        }

        return DB::transaction(function () use ($shift, $admin, $countedCash, $note): Shift {
            return $this->settle($this->lockShift($shift->id), $admin, $countedCash, $note, forceClosed: true);
        });
    }

    /**
     * Review a closed shift (`finance.reviewShift`): Approve or Flag it with a note. A review can be changed later.
     *
     * @param  'approved'|'flagged'  $decision
     *
     * @throws ValidationException
     */
    public function review(Shift $shift, User $reviewer, string $decision, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($shift, $reviewer, $decision, $note): Shift {
            $shift = $this->lockShift($shift->id);

            if ($shift->status !== 'closed') {
                throw ValidationException::withMessages(['shift' => "{$shift->shift_no} is still open — close it before reviewing."]);
            }

            $shift->update([
                'review_status' => $decision,
                'reviewed_at' => now(),
                'reviewed_by_id' => $reviewer->id,
                'reviewed_by_name' => $reviewer->name ?: $reviewer->email,
                'review_note' => trim((string) $note),
            ]);

            return $shift;
        });
    }

    /**
     * Lock a shift that cash is about to be paid out of (an expense or salary "paid from cash drawer"). It must
     * still be open.
     *
     * @throws ValidationException
     */
    public function lockOpenShift(int $shiftId, string $errorKey = 'shift_id'): Shift
    {
        $this->ensureTransaction();

        $shift = Shift::query()->whereKey($shiftId)->lockForUpdate()->first();

        if ($shift === null || $shift->status !== 'open') {
            throw ValidationException::withMessages([
                $errorKey => ($shift === null ? 'That shift' : $shift->shift_no).' is not open — choose an open shift to pay from.',
            ]);
        }

        return $shift;
    }

    /**
     * Pay cash out of a locked open shift's drawer: `cash_expenses_total += amount`, lowering its expected cash.
     */
    public function recordCashExpense(Shift $shift, float|int|string $amount): void
    {
        $this->ensureTransaction();

        $shift->cash_expenses_total = (SupplierService::cents($shift->cash_expenses_total) + SupplierService::cents($amount)) / 100;
        $shift->save();
    }

    /**
     * Put a deleted payout back into a locked shift's drawer (the opposite of recordCashExpense()). Only while
     * the shift is still open — a closed shift's figures are final. Returns whether the shift was changed.
     */
    public function removeCashExpense(Shift $shift, float|int|string $amount): bool
    {
        $this->ensureTransaction();

        if ($shift->status !== 'open') {
            return false;
        }

        $shift->cash_expenses_total = max(0, SupplierService::cents($shift->cash_expenses_total) - SupplierService::cents($amount)) / 100;
        $shift->save();

        return true;
    }

    /**
     * Lock the cashier's open shift for a sale; it must be open and belong to them (SPEC §8.4 checkout step 1).
     *
     * @throws ValidationException
     */
    public function lockOpenShiftFor(User $cashier): Shift
    {
        $this->ensureTransaction();

        $shift = Shift::query()
            ->where('cashier_id', $cashier->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->lockForUpdate()
            ->first();

        if ($shift === null) {
            throw ValidationException::withMessages(['shift' => 'Your shift is not open — reopen a shift before selling.']);
        }

        return $shift;
    }

    /**
     * Add a sale to a locked shift: one more sale, and each payment leg added to its method's total.
     *
     * @param  list<array{method: string, amount: float|int|string}>  $legs
     */
    public function recordSale(Shift $shift, array $legs): void
    {
        $this->ensureTransaction();

        $shift->sales_count++;

        foreach ($legs as $leg) {
            $column = self::SALES_TOTAL_COLUMNS[$leg['method']];
            $shift->{$column} = (SupplierService::cents($shift->{$column}) + SupplierService::cents($leg['amount'])) / 100;
        }

        $shift->save();
    }

    /**
     * Take a reversed sale back out of a locked shift: one sale fewer, and each payment leg subtracted from its
     * method's total (the exact opposite of recordSale()).
     *
     * @param  list<array{method: string, amount: float|int|string}>  $legs
     */
    public function removeSale(Shift $shift, array $legs): void
    {
        $this->ensureTransaction();

        $shift->sales_count = max(0, $shift->sales_count - 1);

        foreach ($legs as $leg) {
            $column = self::SALES_TOTAL_COLUMNS[$leg['method']];
            $shift->{$column} = (SupplierService::cents($shift->{$column}) - SupplierService::cents($leg['amount'])) / 100;
        }

        $shift->save();
    }

    /**
     * Get the cash the drawer should hold: opening float + cash sales − cash paid out.
     */
    public static function expectedCash(Shift $shift): float
    {
        return (SupplierService::cents($shift->opening_float)
            + SupplierService::cents($shift->cash_sales_total)
            - SupplierService::cents($shift->cash_expenses_total)) / 100;
    }

    /**
     * Close a locked open shift with the counted cash. Shared by the cashier's close and the admin force close.
     *
     * @throws ValidationException
     */
    private function settle(Shift $shift, User $closedBy, float|int|string $countedCash, ?string $note, bool $forceClosed): Shift
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages(['shift' => "{$shift->shift_no} is already closed."]);
        }

        $countedCents = SupplierService::cents($countedCash);

        if ($countedCents < 0) {
            throw ValidationException::withMessages(['counted_cash' => "The counted cash can't be negative."]);
        }

        $expectedCents = SupplierService::cents(self::expectedCash($shift));

        $shift->update([
            'status' => 'closed',
            'closed_at' => now(),
            'expected_cash' => $expectedCents / 100,
            'counted_cash' => $countedCents / 100,
            'variance' => ($countedCents - $expectedCents) / 100,
            'close_note' => trim((string) $note),
            'force_closed' => $forceClosed,
            'closed_by_id' => $closedBy->id,
            'closed_by_name' => $closedBy->name ?: $closedBy->email,
            'review_status' => 'pending',
        ]);

        return $shift;
    }

    private function lockShift(int $shiftId): Shift
    {
        return Shift::query()->whereKey($shiftId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @throws LogicException
     */
    private function ensureTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Shift totals must change inside DB::transaction().');
        }
    }
}
