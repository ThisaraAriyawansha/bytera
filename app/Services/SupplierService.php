<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class SupplierService
{
    /**
     * Fields of a recorded payment an edit may change; each change is audit logged.
     *
     * @var list<string>
     */
    public const EDITABLE_PAYMENT_FIELDS = ['amount', 'method', 'reference', 'note'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Work out a supplier's payment status (SPEC §8.12): nothing owed is paid, owing everything is outstanding.
     */
    public static function paymentStatus(float|string $balance, float|string $totalPayable): string
    {
        $balanceCents = self::cents($balance);

        if ($balanceCents <= 0) {
            return 'paid';
        }

        return $balanceCents >= self::cents($totalPayable) ? 'outstanding' : 'partial';
    }

    /**
     * Raise what we owe a supplier, e.g. for a GRN. The supplier must be locked.
     */
    public function addPayable(Supplier $supplier, float|string $amount): void
    {
        $this->ensureTransaction();

        $this->applyTotals($supplier, self::cents($supplier->total_payable) + self::cents($amount), self::cents($supplier->amount_paid));
        $supplier->save();
    }

    /**
     * Record a payment to a supplier as a new PAY- record, lowering the balance (SPEC §8.18).
     *
     * @param  array{amount: float|string, method: string, reference?: ?string, note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function recordPayment(Supplier $supplier, array $data, User $paidBy): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data, $paidBy): SupplierPayment {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $amountCents = self::cents($data['amount']);
            $balanceCents = self::cents($supplier->balance);

            $this->ensureWithinBalance($amountCents, $balanceCents);

            $payment = $supplier->payments()->create([
                'payment_no' => Numbering::next('supplierPayment', Numbering::PREFIXES['supplierPayment']),
                'supplier_name' => $supplier->name,
                'amount' => $amountCents / 100,
                'method' => $data['method'],
                'reference' => (string) ($data['reference'] ?? ''),
                'note' => (string) ($data['note'] ?? ''),
                'balance_before' => $balanceCents / 100,
                'balance_after' => ($balanceCents - $amountCents) / 100,
                'paid_by_id' => $paidBy->id,
                'paid_by_name' => $paidBy->name ?: $paidBy->email,
            ]);

            $this->applyTotals($supplier, self::cents($supplier->total_payable), self::cents($supplier->amount_paid) + $amountCents);
            $supplier->last_payment_at = now();
            $supplier->save();

            return $payment;
        });
    }

    /**
     * Edit a recorded payment. A new amount recalculates the supplier's amount paid, balance and status,
     * and may not pay more than is owed. Changed fields are audit logged.
     *
     * @param  array{amount: float|string, method: string, reference?: ?string, note?: ?string}  $data
     * @return list<array{field: string, before: mixed, after: mixed}> the changes made
     *
     * @throws ValidationException
     */
    public function updatePayment(SupplierPayment $payment, array $data, User $editor): array
    {
        return DB::transaction(function () use ($payment, $data, $editor): array {
            $supplier = Supplier::query()->whereKey($payment->supplier_id)->lockForUpdate()->firstOrFail();
            $payment = SupplierPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $patch = [
                'amount' => self::cents($data['amount']) / 100,
                'method' => $data['method'],
                'reference' => (string) ($data['reference'] ?? ''),
                'note' => (string) ($data['note'] ?? ''),
            ];

            $changes = $this->audit->diff($payment->only(self::EDITABLE_PAYMENT_FIELDS), $patch, self::EDITABLE_PAYMENT_FIELDS);

            if ($changes === []) {
                return [];
            }

            $oldAmountCents = self::cents($payment->amount);
            $newAmountCents = self::cents($patch['amount']);

            if ($newAmountCents !== $oldAmountCents) {
                $this->ensureWithinBalance($newAmountCents, self::cents($supplier->balance) + $oldAmountCents);

                $this->applyTotals(
                    $supplier,
                    self::cents($supplier->total_payable),
                    self::cents($supplier->amount_paid) - $oldAmountCents + $newAmountCents,
                );
                $supplier->save();

                $patch['balance_after'] = (self::cents($payment->balance_before) - $newAmountCents) / 100;
            }

            $payment->update($patch);

            $this->audit->write($payment->getTable(), $payment->id, $payment->payment_no, $changes, $editor);

            return $changes;
        });
    }

    /**
     * Convert an amount to whole cents so balances are compared and summed exactly.
     */
    public static function cents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Set the supplier's totals from cents and recompute the balance and status.
     */
    private function applyTotals(Supplier $supplier, int $totalPayableCents, int $amountPaidCents): void
    {
        $balanceCents = $totalPayableCents - $amountPaidCents;

        $supplier->total_payable = $totalPayableCents / 100;
        $supplier->amount_paid = $amountPaidCents / 100;
        $supplier->balance = $balanceCents / 100;
        $supplier->payment_status = self::paymentStatus($balanceCents / 100, $totalPayableCents / 100);
    }

    /**
     * Refuse a payment larger than what is owed.
     *
     * @throws ValidationException
     */
    private function ensureWithinBalance(int $amountCents, int $balanceCents): void
    {
        if ($amountCents > $balanceCents) {
            throw ValidationException::withMessages([
                'amount' => 'Payment of '.Money::format($amountCents / 100).' exceeds the outstanding balance of '.Money::format(max(0, $balanceCents) / 100),
            ]);
        }
    }

    /**
     * @throws LogicException
     */
    private function ensureTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Supplier balances must be changed inside DB::transaction().');
        }
    }
}
