<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\User;
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
     * Get the cash the drawer should hold: opening float + cash sales − cash paid out.
     */
    public static function expectedCash(Shift $shift): float
    {
        return (SupplierService::cents($shift->opening_float)
            + SupplierService::cents($shift->cash_sales_total)
            - SupplierService::cents($shift->cash_expenses_total)) / 100;
    }

    /**
     * Close a locked open shift with the counted cash. Shared by the cashier's close and (later) the admin force close.
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
