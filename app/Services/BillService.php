<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillService
{
    /**
     * Fields an Edit Bill may change (SPEC §8.5), compared for the audit log.
     *
     * @var list<string>
     */
    public const EDITABLE_FIELDS = [
        'customer_name',
        'customer_phone',
        'customer_email',
        'note',
        'payment_method',
    ];

    public function __construct(
        private StockService $stock,
        private ShiftService $shifts,
        private AuditLogger $audit,
    ) {}

    /**
     * Edit Bill (`bills.edit`): customer name / phone / email, note and payment method, audit logged in the same
     * transaction. Cancelled bills can't be edited.
     *
     * A split bill's payment method can't change (its legs are what the shift recorded). Moving a single-method
     * bill to another method also moves its total between the shift's method totals while the shift is still
     * open, so the drawer figures and a later reversal stay right.
     *
     * @param  array{customer_name: string, customer_phone?: ?string, customer_email?: ?string, note?: ?string, payment_method: string}  $data
     * @return list<array{field: string, before: mixed, after: mixed}>
     *
     * @throws ValidationException
     */
    public function update(Sale $sale, array $data, User $editor): array
    {
        return DB::transaction(function () use ($sale, $data, $editor): array {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === 'cancelled') {
                throw ValidationException::withMessages(['bill' => "{$sale->invoice_no} has been reversed and can't be edited."]);
            }

            $columns = array_intersect_key($data, array_flip(self::EDITABLE_FIELDS));
            $changes = $this->audit->diff($sale->only(self::EDITABLE_FIELDS), $columns, self::EDITABLE_FIELDS);

            if ($changes === []) {
                return [];
            }

            if ($columns['payment_method'] !== $sale->payment_method) {
                $this->changePaymentMethod($sale, $columns['payment_method']);
            }

            $sale->update(array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $columns));

            $this->audit->write($sale->getTable(), $sale->id, $sale->invoice_no, $changes, $editor);

            return $changes;
        });
    }

    /**
     * Reverse Bill (`bills.cancel`, SPEC §8.5) in one transaction, putting everything the sale changed back:
     *
     * - the sale is marked cancelled with who / when / why (it stays listed, marked Cancelled);
     * - if its shift is still open, its payment legs come off the shift totals and `sales_count` drops by one;
     * - stock goes back to the Showroom: product counters, the exact batches it was taken from
     *   (`batch_allocations`), and serial units back to `in_stock` with the sale cleared;
     * - a `sale_cancel` stock movement per line ("Cancelled INV-xxxxx");
     * - loyalty points: + redeemed − earned;
     * - the sale's warranties are deleted, and restocked products have `low_stock_alerted` reset.
     *
     * @throws ValidationException
     */
    public function reverse(Sale $sale, string $reason, User $user): Sale
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter the reason for reversing this bill.']);
        }

        return DB::transaction(function () use ($sale, $reason, $user): Sale {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === 'cancelled') {
                throw ValidationException::withMessages(['bill' => "{$sale->invoice_no} has already been reversed."]);
            }

            $shift = $sale->shift_id === null ? null : Shift::query()->whereKey($sale->shift_id)->lockForUpdate()->first();

            if ($shift?->status === 'open') {
                $this->shifts->removeSale($shift, array_map(
                    fn (array $leg): array => ['method' => $leg['method'], 'amount' => $leg['amount']],
                    $sale->paymentLegs(),
                ));
            }

            $items = $sale->items()->orderBy('id')->get();
            $products = Product::query()
                ->whereKey($items->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products[$item->product_id];

                $this->restoreBatches($item, $product);
                $this->restoreUnits($item, $sale);
                $this->stock->adjustStock($product, 'showroom', $item->qty);

                $this->stock->recordMovement($product, 'in', $item->qty, 'sale_cancel', $sale->id, "Cancelled {$sale->invoice_no}", $user, [
                    'location' => 'showroom',
                ]);
            }

            $this->reverseLoyalty($sale);

            $sale->warranties()->delete();

            $sale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_id' => $user->id,
                'cancelled_by_name' => $user->name ?: $user->email,
                'cancel_reason' => $reason,
            ]);

            return $sale;
        });
    }

    /**
     * Put a line's quantity back into the exact batches it was taken from. Serial lines without allocations
     * (none are written that way today) fall back to their units' batches.
     *
     * @throws ValidationException
     */
    private function restoreBatches(SaleItem $item, Product $product): void
    {
        $allocations = $item->batch_allocations
            ?? collect($item->units ?? [])->countBy('batchId')->map(fn (int $qty, int $batchId): array => ['batchId' => $batchId, 'qty' => $qty])->values()->all();

        $batches = ProductBatch::query()
            ->whereKey(array_column($allocations, 'batchId'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($allocations as $allocation) {
            $batch = $batches[$allocation['batchId']] ?? null;

            if ($batch === null || (int) $batch->product_id !== $product->id) {
                throw ValidationException::withMessages([
                    'bill' => "A stock batch of \"{$item->product_name}\" on this bill no longer exists, so the bill can't be reversed.",
                ]);
            }

            $batch->remaining_qty += (int) $allocation['qty'];
            $batch->status = $batch->remaining_qty > 0 ? 'active' : 'depleted';
            $batch->save();
        }
    }

    /**
     * Put a serial line's units back in stock in the Showroom, with the sale cleared.
     *
     * @throws ValidationException
     */
    private function restoreUnits(SaleItem $item, Sale $sale): void
    {
        $unitIds = array_column($item->units ?? [], 'unitId');

        if ($unitIds === []) {
            return;
        }

        $units = ProductUnit::query()->whereKey($unitIds)->orderBy('id')->lockForUpdate()->get();

        $missing = $units->count() !== count(array_unique($unitIds))
            || $units->contains(fn (ProductUnit $unit): bool => $unit->status !== 'sold' || (int) $unit->sale_id !== $sale->id);

        if ($missing) {
            throw ValidationException::withMessages([
                'bill' => "A serial unit of \"{$item->product_name}\" on this bill has changed since the sale, so the bill can't be reversed.",
            ]);
        }

        $units->toQuery()->update([
            'status' => 'in_stock',
            'location' => 'showroom',
            'sale_id' => null,
            'sold_at' => null,
        ]);
    }

    /**
     * Give back the points the bill redeemed and take back the points it earned.
     */
    private function reverseLoyalty(Sale $sale): void
    {
        if ($sale->customer_id === null) {
            return;
        }

        $customer = Customer::query()->whereKey($sale->customer_id)->lockForUpdate()->first();

        if ($customer === null) {
            return;
        }

        $earned = SaleService::pointsEarned(SupplierService::cents($sale->subtotal), SupplierService::cents($sale->discount_amount));

        $customer->loyalty_points += $sale->points_redeemed - $earned;
        $customer->save();
    }

    /**
     * Move a single-method bill to another method. While its shift is open the total moves between the shift's
     * method totals too.
     *
     * @throws ValidationException
     */
    private function changePaymentMethod(Sale $sale, string $method): void
    {
        if (count($sale->payments ?? []) > 1) {
            throw ValidationException::withMessages([
                'payment_method' => "This bill was paid {$sale->paymentLabel()} — a split payment's method can't be changed.",
            ]);
        }

        $shift = $sale->shift_id === null ? null : Shift::query()->whereKey($sale->shift_id)->lockForUpdate()->first();

        if ($shift?->status !== 'open') {
            return;
        }

        $this->shifts->removeSale($shift, [['method' => $sale->payment_method, 'amount' => $sale->total_amount]]);
        $this->shifts->recordSale($shift, [['method' => $method, 'amount' => $sale->total_amount]]);
    }
}
