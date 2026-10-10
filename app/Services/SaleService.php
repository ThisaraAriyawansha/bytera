<?php

namespace App\Services;

use App\Mail\LowStockAlertMail;
use App\Models\Customer;
use App\Models\Job;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\User;
use App\Rules\StrictEmail;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaleService
{
    /**
     * The customer name stored and printed when no customer is selected.
     */
    public const WALK_IN_CUSTOMER = 'Walk-in Customer';

    /**
     * Rupees spent per loyalty point earned (1 point = Rs. 1 when redeemed).
     */
    public const RUPEES_PER_POINT = 100;

    public function __construct(
        private StockService $stock,
        private ShiftService $shifts,
        private JobService $jobs,
    ) {}

    /**
     * Check out a POS sale in one transaction (SPEC §8.4 steps 1–12), then send the low stock alert
     * for any product that just crossed its threshold.
     *
     * Prices and totals are worked out here from the database; the browser only sends what it picked
     * (products, units, batches, service prices, discounts, payment legs) plus the total it showed, which
     * must match.
     *
     * @param  array{
     *     customer_id?: ?int,
     *     job_id?: ?int,
     *     items?: list<array{product_id: int, qty?: ?int, batch_id?: ?int, unit_ids?: list<int>, discount?: float|int|string|null}>,
     *     services?: list<array{service_id: int, price: float|int|string, fields?: array<string, mixed>}>,
     *     discount_amount?: float|int|string|null,
     *     points_redeemed?: ?int,
     *     payments: list<array{method: string, amount?: float|int|string|null}>,
     *     card_charge_percent?: float|int|string|null,
     *     kokopay_charge_percent?: float|int|string|null,
     *     amount_tendered?: float|int|string|null,
     *     expected_total: float|int|string,
     *     note?: ?string,
     * }  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $cashier): Sale
    {
        $lowStockProducts = new Collection;

        $sale = DB::transaction(function () use ($data, $cashier, &$lowStockProducts): Sale {
            // 1. The cashier's own open shift.
            $shift = $this->shifts->lockOpenShiftFor($cashier);

            $items = array_values($data['items'] ?? []);
            $products = Product::query()
                ->whereKey(collect($items)->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // A finished job being billed; its customer is the bill's customer when none is selected.
            $job = blank($data['job_id'] ?? null) ? null : $this->lockBillableJob((int) $data['job_id']);
            $customerId = blank($data['customer_id'] ?? null) ? $job?->customer_id : $data['customer_id'];

            $customer = $customerId === null
                ? null
                : Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();

            // 2. Serial units, then 3. showroom capacity for everything else.
            $productLines = $this->productLines($items, $products);
            $jobLines = $job === null ? [] : $this->jobLines($job);
            $serviceLines = $this->serviceLines(array_values($data['services'] ?? []));

            if ($productLines === [] && $jobLines === [] && $serviceLines === []) {
                throw ValidationException::withMessages(['items' => 'The cart is empty.']);
            }

            $this->ensureShowroomCapacity($productLines, $products);

            $quote = self::quote(
                [...$productLines, ...$jobLines, ...$serviceLines],
                $data['discount_amount'] ?? 0,
                (int) ($data['points_redeemed'] ?? 0),
                $data['payments'],
                $data['card_charge_percent'] ?? null,
                $data['kokopay_charge_percent'] ?? null,
            );

            $this->ensurePointsAvailable($quote['pointsRedeemed'], $customer);
            $this->ensureExpectedTotal($quote['totalCents'], $data['expected_total']);
            [$tenderedCents, $changeCents] = $this->cashTendered($quote, $data['amount_tendered'] ?? null);

            // 4. Number, sale and items.
            $invoiceNo = Numbering::next('invoice', Numbering::PREFIXES['invoice']);
            $legs = $quote['legs'];

            $sale = Sale::query()->create([
                'invoice_no' => $invoiceNo,
                'customer_id' => $customer?->id,
                'customer_name' => $customer->name ?? $job->customer_name ?? self::WALK_IN_CUSTOMER,
                'customer_phone' => $customer?->phone ?? (filled($job?->customer_phone) ? $job->customer_phone : null),
                'customer_email' => $customer?->email ?? (filled($job?->customer_email) ? $job->customer_email : null),
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name ?: $cashier->email,
                'job_id' => $job?->id,
                'job_no' => $job?->job_no,
                'services' => $jobLines === [] && $serviceLines === []
                    ? null
                    : [...$this->jobSnapshots($job, $jobLines, $quote, count($productLines)), ...$this->serviceSnapshots($serviceLines, $quote)],
                'subtotal' => $quote['subtotalCents'] / 100,
                'discount_amount' => $quote['discountCents'] / 100,
                'tax_amount' => 0,
                'total_amount' => $quote['totalCents'] / 100,
                'payment_method' => $quote['paymentMethod'],
                'payments' => count($legs) > 1 ? array_map(fn (array $leg): array => ['method' => $leg['method'], 'amount' => $leg['amountCents'] / 100], $legs) : null,
                ...$this->chargeColumns($quote),
                'payment_status' => 'paid',
                'amount_tendered' => $tenderedCents === null ? null : $tenderedCents / 100,
                'change_amount' => $changeCents === null ? null : $changeCents / 100,
                'points_redeemed' => $quote['pointsRedeemed'],
                'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
                'shift_id' => $shift->id,
                'shift_no' => $shift->shift_no,
            ]);

            // 5. Shift totals per payment leg.
            $this->shifts->recordSale($shift, array_map(fn (array $leg): array => ['method' => $leg['method'], 'amount' => $leg['amountCents'] / 100], $legs));

            foreach ($productLines as $index => $line) {
                $product = $products[$line['product_id']];

                // 6. Stock, batches and units.
                [$costPrice, $allocations, $unitSnapshots] = $line['units'] === null
                    ? $this->consumeBatches($product, $line)
                    : $this->sellUnits($line['units'], $sale);

                $this->stock->adjustStock($product, 'showroom', -$line['qty']);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'batch_id' => $line['batch_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $quote['unitPrices'][$index],
                    'cost_price' => $costPrice,
                    'discount' => $line['discountCents'] / 100,
                    'line_total' => $quote['lineTotalsCents'][$index] / 100,
                    'warranty_months' => $product->warranty_months,
                    'units' => $unitSnapshots,
                    'batch_allocations' => $allocations,
                ]);

                // 7. Movement.
                $this->stock->recordMovement($product, 'out', -$line['qty'], 'sale', $sale->id, "Sale {$invoiceNo}", $cashier, [
                    'location' => 'showroom',
                ]);

                // 8. Warranties: one per serial unit, or one per line.
                $this->issueWarranties($sale, $product, $unitSnapshots, $customer);
            }

            // 9. Low stock flag (the email goes out after commit).
            $lowStockProducts = $this->flagLowStock($products);

            // 10. Loyalty points.
            if ($customer !== null) {
                $customer->loyalty_points += self::pointsEarned($quote['subtotalCents'], $quote['discountCents']) - $quote['pointsRedeemed'];
                $customer->save();
            }

            // 11. A billed job is Delivered, with a history row naming the invoice.
            if ($job !== null) {
                $this->jobs->deliverBilledJob($job, $invoiceNo, $cashier);
            }

            return $sale;
        });

        $this->sendLowStockAlert($lowStockProducts);

        return $sale;
    }

    /**
     * Work out a bill the way the POS cart does (SPEC §8.4), in whole cents, including the Card / KokoPay
     * surcharge and the payment legs.
     *
     * - KokoPay, or Card as the only method: every surchargeable line price × (1 + %/100), rounded.
     * - Card in a split: card base = max(0, baseSubtotal − discount − points − otherLegs),
     *   fee = round(cardBase × %/100), multiplier = 1 + fee / baseSubtotal, and the card leg = total − otherLegs.
     *
     * The arithmetic mirrors resources/js/components/pos-cart.js step for step (`floor(x + 0.5)` is
     * JavaScript's Math.round) so both sides land on the same rupee.
     *
     * @param  list<array{price: float|int|string, discountCents: int, qty: int, surcharge: bool}>  $lines
     * @param  list<array{method: string, amount?: float|int|string|null}>  $payments
     * @return array{multiplier: float, unitPrices: list<float>, lineTotalsCents: list<int>, baseSubtotalCents: int, subtotalCents: int, discountCents: int, pointsRedeemed: int, totalCents: int, chargeMethod: ?string, chargePercent: ?float, chargeAmountCents: int, legs: list<array{method: string, amountCents: int}>, paymentMethod: string}
     *
     * @throws ValidationException
     */
    public static function quote(
        array $lines,
        float|int|string|null $discount,
        int $pointsRedeemed,
        array $payments,
        float|int|string|null $cardPercent,
        float|int|string|null $kokopayPercent,
    ): array {
        $methods = array_values(array_unique(array_column($payments, 'method')));
        $isSplit = count($methods) > 1;
        $cardPercent = (float) $cardPercent;
        $kokopayPercent = (float) $kokopayPercent;

        if ($methods === []) {
            throw ValidationException::withMessages(['payments' => 'Choose a payment method.']);
        }

        if ($isSplit && in_array('kokopay', $methods, true)) {
            throw ValidationException::withMessages(['payments' => "KokoPay can't be combined with other payment methods."]);
        }

        if ($cardPercent < 0 || $kokopayPercent < 0 || $cardPercent > 100 || $kokopayPercent > 100) {
            throw ValidationException::withMessages(['payments' => 'The surcharge must be between 0 and 100%.']);
        }

        $discountCents = SupplierService::cents($discount);
        $pointsCents = $pointsRedeemed * 100;
        $otherLegsCents = 0;

        foreach ($payments as $payment) {
            if ($isSplit && $payment['method'] !== 'card') {
                $otherLegsCents += SupplierService::cents($payment['amount'] ?? 0);
            }
        }

        $baseSubtotalCents = 0;

        foreach ($lines as $line) {
            $baseSubtotalCents += (SupplierService::cents($line['price']) - $line['discountCents']) * $line['qty'];
        }

        $multiplier = 1.0;
        $chargeMethod = null;
        $chargePercent = null;

        if (! $isSplit && $methods === ['kokopay'] && $kokopayPercent > 0) {
            [$multiplier, $chargeMethod, $chargePercent] = [1 + $kokopayPercent / 100, 'kokopay', $kokopayPercent];
        } elseif (! $isSplit && $methods === ['card'] && $cardPercent > 0) {
            [$multiplier, $chargeMethod, $chargePercent] = [1 + $cardPercent / 100, 'card', $cardPercent];
        } elseif ($isSplit && in_array('card', $methods, true) && $cardPercent > 0 && $baseSubtotalCents > 0) {
            $cardBase = max(0, ($baseSubtotalCents - $discountCents - $pointsCents - $otherLegsCents) / 100);
            $fee = floor($cardBase * $cardPercent / 100 + 0.5);
            [$multiplier, $chargeMethod, $chargePercent] = [1 + $fee / ($baseSubtotalCents / 100), 'card', $cardPercent];
        }

        $unitPrices = [];
        $lineTotalsCents = [];

        foreach ($lines as $line) {
            $price = (float) $line['price'];
            $price = $multiplier === 1.0 || ! $line['surcharge'] ? $price : floor($price * $multiplier + 0.5);
            $unitPrices[] = SupplierService::cents($price) / 100;
            $lineTotalsCents[] = (SupplierService::cents($price) - $line['discountCents']) * $line['qty'];
        }

        $subtotalCents = array_sum($lineTotalsCents);

        if ($discountCents < 0 || $discountCents > $subtotalCents) {
            throw ValidationException::withMessages(['discount_amount' => 'The bill discount must be between 0 and the subtotal.']);
        }

        if ($pointsRedeemed < 0 || $pointsCents > $subtotalCents - $discountCents) {
            throw ValidationException::withMessages(['points_redeemed' => "You can't redeem more points than the bill after discount."]);
        }

        $totalCents = $subtotalCents - $discountCents - $pointsCents;
        $legs = self::paymentLegs($payments, $methods, $totalCents);

        return [
            'multiplier' => $multiplier,
            'unitPrices' => $unitPrices,
            'lineTotalsCents' => $lineTotalsCents,
            'baseSubtotalCents' => $baseSubtotalCents,
            'subtotalCents' => $subtotalCents,
            'discountCents' => $discountCents,
            'pointsRedeemed' => $pointsRedeemed,
            'totalCents' => $totalCents,
            'chargeMethod' => $chargeMethod,
            'chargePercent' => $chargePercent,
            'chargeAmountCents' => $subtotalCents - $baseSubtotalCents,
            'legs' => $legs,
            'paymentMethod' => collect($legs)->sortByDesc('amountCents')->first()['method'],
        ];
    }

    /**
     * Loyalty points a bill earns: 1 point per Rs. 100 of subtotal after the bill discount.
     */
    public static function pointsEarned(int $subtotalCents, int $discountCents): int
    {
        return intdiv(max(0, $subtotalCents - $discountCents), self::RUPEES_PER_POINT * 100);
    }

    /**
     * Split the total into payment legs. One method pays it all; in a split each typed leg must be above zero and
     * either the card leg takes the remainder or the legs must add up exactly.
     *
     * @param  list<array{method: string, amount?: float|int|string|null}>  $payments
     * @param  list<string>  $methods
     * @return list<array{method: string, amountCents: int}>
     *
     * @throws ValidationException
     */
    private static function paymentLegs(array $payments, array $methods, int $totalCents): array
    {
        if (count($methods) === 1) {
            return [['method' => $methods[0], 'amountCents' => $totalCents]];
        }

        $legs = [];
        $typedCents = 0;

        foreach ($payments as $payment) {
            if ($payment['method'] === 'card') {
                continue;
            }

            $amountCents = SupplierService::cents($payment['amount'] ?? 0);

            if ($amountCents <= 0) {
                throw ValidationException::withMessages(['payments' => 'Enter an amount above zero for '.Sale::PAYMENT_METHODS[$payment['method']].'.']);
            }

            $typedCents += $amountCents;
            $legs[$payment['method']] = ['method' => $payment['method'], 'amountCents' => $amountCents];
        }

        if (in_array('card', $methods, true)) {
            $cardCents = $totalCents - $typedCents;

            if ($cardCents <= 0) {
                throw ValidationException::withMessages(['payments' => 'The other payments already cover the total — the card amount must be above zero.']);
            }

            $legs['card'] = ['method' => 'card', 'amountCents' => $cardCents];
        } elseif ($typedCents !== $totalCents) {
            $difference = Money::format(abs($totalCents - $typedCents) / 100);

            throw ValidationException::withMessages([
                'payments' => $typedCents < $totalCents
                    ? "The payments don't cover the total — Remaining: {$difference}."
                    : "The payments are over the total by {$difference}.",
            ]);
        }

        return array_values(array_map(fn (string $method): array => $legs[$method], $methods));
    }

    /**
     * Build the product lines and check serial units (step 2). Units must be in stock in the Showroom; a batch the
     * cashier picked must be an active Showroom batch of the product. Unit price = batch price ?? product price.
     *
     * @param  list<array{product_id: int, qty?: ?int, batch_id?: ?int, unit_ids?: list<int>, discount?: float|int|string|null}>  $items
     * @param  Collection<int, Product>  $products
     * @return list<array{product_id: int, qty: int, batch_id: ?int, units: ?Collection<int, ProductUnit>, price: float|int|string, discountCents: int, surcharge: bool}>
     *
     * @throws ValidationException
     */
    private function productLines(array $items, Collection $products): array
    {
        $lines = [];

        foreach ($items as $index => $item) {
            $product = $products[$item['product_id']];

            if (! $product->active) {
                throw ValidationException::withMessages(["items.{$index}" => "\"{$product->name}\" is no longer for sale."]);
            }

            $units = null;
            $batchId = null;

            if ($product->track_serial) {
                $units = $this->lockSellableUnits($product, $item['unit_ids'] ?? [], $index);
                $prices = $units->map(fn (ProductUnit $unit): int => SupplierService::cents($unit->selling_price ?? $product->selling_price))->unique();

                if ($prices->count() > 1) {
                    throw ValidationException::withMessages(["items.{$index}" => "The serials picked for \"{$product->name}\" have different prices — add them as separate lines."]);
                }

                $price = $prices->first() / 100;
                $qty = $units->count();
            } else {
                $qty = (int) ($item['qty'] ?? 0);
                $price = $product->selling_price;

                if (filled($item['batch_id'] ?? null)) {
                    $batch = ProductBatch::query()
                        ->whereKey($item['batch_id'])
                        ->where('product_id', $product->id)
                        ->where('location', 'showroom')
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();

                    if ($batch === null) {
                        throw ValidationException::withMessages(["items.{$index}" => "The batch picked for \"{$product->name}\" is no longer in the Showroom — choose again."]);
                    }

                    $batchId = $batch->id;
                    $price = $batch->selling_price ?? $product->selling_price;
                }
            }

            if ($qty < 1) {
                throw ValidationException::withMessages(["items.{$index}" => "Enter a quantity for \"{$product->name}\"."]);
            }

            $discountCents = SupplierService::cents($item['discount'] ?? 0);

            if ($discountCents < 0 || $discountCents > SupplierService::cents($price)) {
                throw ValidationException::withMessages(["items.{$index}.discount" => "The discount on \"{$product->name}\" can't be more than its price."]);
            }

            $lines[] = [
                'product_id' => $product->id,
                'qty' => $qty,
                'batch_id' => $batchId,
                'units' => $units,
                'price' => $price,
                'discountCents' => $discountCents,
                'surcharge' => true,
            ];
        }

        return $lines;
    }

    /**
     * Lock the picked serial units; each must still be in stock in the Showroom.
     *
     * @param  list<int>  $unitIds
     * @return Collection<int, ProductUnit>
     *
     * @throws ValidationException
     */
    private function lockSellableUnits(Product $product, array $unitIds, int $index): Collection
    {
        $units = ProductUnit::query()
            ->where('product_id', $product->id)
            ->whereKey($unitIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($unitIds === [] || $units->count() !== count(array_unique($unitIds))) {
            throw ValidationException::withMessages(["items.{$index}" => "Pick the serial numbers for \"{$product->name}\" again."]);
        }

        $unavailable = $units->first(fn (ProductUnit $unit): bool => $unit->status !== 'in_stock' || $unit->location !== 'showroom');

        if ($unavailable !== null) {
            throw ValidationException::withMessages(["items.{$index}" => "\"{$unavailable->serial_number}\" is no longer available for sale"]);
        }

        return $units;
    }

    /**
     * Step 3: lock each product's Showroom batches and check they hold what the cart asks for.
     *
     * @param  list<array{product_id: int, qty: int, units: ?Collection<int, ProductUnit>}>  $lines
     * @param  Collection<int, Product>  $products
     *
     * @throws ValidationException
     */
    private function ensureShowroomCapacity(array $lines, Collection $products): void
    {
        $requested = [];

        foreach ($lines as $line) {
            if ($line['units'] === null) {
                $requested[$line['product_id']] = ($requested[$line['product_id']] ?? 0) + $line['qty'];
            }
        }

        foreach ($requested as $productId => $qty) {
            $inShowroom = (int) ProductBatch::query()
                ->where('product_id', $productId)
                ->where('location', 'showroom')
                ->where('status', 'active')
                ->lockForUpdate()
                ->get(['id', 'remaining_qty'])
                ->sum('remaining_qty');

            if ($inShowroom < $qty) {
                $product = $products[$productId];
                $inStores = $product->stores_stock;

                throw ValidationException::withMessages([
                    'stock' => "Not enough Showroom stock for \"{$product->name}\": requested {$qty}, only {$inShowroom} in Showroom"
                        .($inStores > 0 ? " ({$inStores} more in Stores — transfer to Showroom first.)" : '.'),
                ]);
            }
        }
    }

    /**
     * Build the quick-service lines: active services at the price the cashier entered, with their custom fields
     * checked (required text filled, required checkboxes ticked, dropdown values from the list).
     *
     * @param  list<array{service_id: int, price: float|int|string, fields?: array<string, mixed>}>  $services
     * @return list<array{service: Service, fields: list<array{id: string, label: string, type: string, value: mixed}>, price: float|int|string, discountCents: int, qty: int, surcharge: bool}>
     *
     * @throws ValidationException
     */
    private function serviceLines(array $services): array
    {
        $models = Service::query()->whereKey(array_column($services, 'service_id'))->get()->keyBy('id');
        $lines = [];

        foreach ($services as $index => $entry) {
            $service = $models[$entry['service_id']] ?? null;

            if ($service === null || ! $service->active) {
                throw ValidationException::withMessages(["services.{$index}" => 'A service in the bill is no longer offered.']);
            }

            $lines[] = [
                'service' => $service,
                'fields' => $this->serviceFieldValues($service, $entry['fields'] ?? [], $index),
                'price' => $entry['price'],
                'discountCents' => 0,
                'qty' => 1,
                'surcharge' => true,
            ];
        }

        return $lines;
    }

    /**
     * Lock the job being billed; only a job marked Job Done can be billed (and only once).
     *
     * @throws ValidationException
     */
    private function lockBillableJob(int $jobId): Job
    {
        $job = Job::query()->whereKey($jobId)->lockForUpdate()->first();

        if ($job === null) {
            throw ValidationException::withMessages(['job_id' => 'The job to bill no longer exists.']);
        }

        if ($job->status !== 'done') {
            throw ValidationException::withMessages([
                'job_id' => "{$job->job_no} can't be billed — it is {$job->statusLabel()}. Only jobs marked Job Done can be billed.",
            ]);
        }

        $activeInvoiceNo = $job->sales()->whereNull('status')->value('invoice_no');

        if ($activeInvoiceNo !== null) {
            throw ValidationException::withMessages([
                'job_id' => "{$job->job_no} has already been billed on {$activeInvoiceNo}. Reverse that bill to bill the job again.",
            ]);
        }

        return $job;
    }

    /**
     * The job's billable lines (services, repair charge / adjustment, less advance) as bill lines, worked out
     * from the job as saved.
     *
     * @return list<array{name: string, price: float, chargeType: string, freeReason: string, discountCents: int, qty: int, surcharge: bool}>
     */
    private function jobLines(Job $job): array
    {
        return array_map(
            fn (array $line): array => [...$line, 'discountCents' => 0, 'qty' => 1],
            JobService::billableLines($job),
        );
    }

    /**
     * The job's lines as stored in the sale's `services` JSON and printed "Name (Service · JOB-xxxxx)", at the
     * surcharged price.
     *
     * @param  list<array{name: string, price: float, chargeType: string, freeReason: string}>  $jobLines
     * @param  array{unitPrices: list<float>}  $quote
     * @return list<array<string, mixed>>
     */
    private function jobSnapshots(?Job $job, array $jobLines, array $quote, int $offset): array
    {
        return array_map(fn (array $line, int $index): array => [
            'source' => 'job',
            'jobId' => $job->id,
            'jobNo' => $job->job_no,
            'name' => $line['name'],
            'price' => $quote['unitPrices'][$offset + $index],
            'basePrice' => $line['price'],
            'chargeType' => $line['chargeType'],
            'freeReason' => $line['freeReason'],
            'fields' => [],
        ], $jobLines, array_keys($jobLines));
    }

    /**
     * Check a service's custom field values and return them with their labels, in the service's field order.
     *
     * @param  array<string, mixed>  $values
     * @return list<array{id: string, label: string, type: string, value: mixed}>
     *
     * @throws ValidationException
     */
    private function serviceFieldValues(Service $service, array $values, int $index): array
    {
        $fields = [];

        foreach ($service->custom_fields ?? [] as $field) {
            $label = $field['label'];
            $value = $values[$field['id']] ?? null;
            $key = "services.{$index}.fields.{$field['id']}";

            if ($field['type'] === 'checkbox') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);

                if (($field['required'] ?? false) && ! $value) {
                    throw ValidationException::withMessages([$key => "\"{$label}\" must be ticked."]);
                }
            } else {
                $value = is_scalar($value) ? trim((string) $value) : '';

                if (($field['required'] ?? false) && $value === '') {
                    throw ValidationException::withMessages([$key => "\"{$label}\" is required."]);
                }

                if ($value !== '' && $field['type'] === 'number' && ! is_numeric($value)) {
                    throw ValidationException::withMessages([$key => "\"{$label}\" must be a number."]);
                }

                if ($value !== '' && $field['type'] === 'select' && ! in_array($value, $field['options'] ?? [], true)) {
                    throw ValidationException::withMessages([$key => "Choose one of the options for \"{$label}\"."]);
                }

                if ($value !== '' && $field['type'] === 'date' && strtotime($value) === false) {
                    throw ValidationException::withMessages([$key => "\"{$label}\" must be a date."]);
                }
            }

            $fields[] = ['id' => $field['id'], 'label' => $label, 'type' => $field['type'], 'value' => $value];
        }

        return $fields;
    }

    /**
     * The `services` JSON stored on the sale and printed on the bill, at the surcharged price.
     *
     * @param  list<array{service: Service, fields: list<array{id: string, label: string, type: string, value: mixed}>, price: float|int|string}>  $serviceLines
     * @param  array{unitPrices: list<float>}  $quote
     * @return list<array<string, mixed>>
     */
    private function serviceSnapshots(array $serviceLines, array $quote): array
    {
        $offset = count($quote['unitPrices']) - count($serviceLines);

        return array_map(fn (array $line, int $index): array => [
            'source' => 'service',
            'serviceId' => $line['service']->id,
            'name' => $line['service']->name,
            'price' => $quote['unitPrices'][$offset + $index],
            'basePrice' => SupplierService::cents($line['price']) / 100,
            'chargeType' => 'paid',
            'fields' => array_map(fn (array $field): array => ['label' => $field['label'], 'type' => $field['type'], 'value' => $field['value']], $line['fields']),
        ], $serviceLines, array_keys($serviceLines));
    }

    /**
     * FIFO-consume Showroom batches for a non-serial line (the picked batch first).
     *
     * @param  array{qty: int, batch_id: ?int}  $line
     * @return array{0: float, 1: list<array{batchId: int, qty: int}>, 2: null}
     *
     * @throws ValidationException
     */
    private function consumeBatches(Product $product, array $line): array
    {
        $allocation = $this->stock->allocateFifo($product, 'showroom', $line['qty'], $line['batch_id']);
        $this->stock->applyAllocation($allocation);

        return [
            $allocation['costPrice'],
            array_map(fn (array $consumed): array => ['batchId' => $consumed['batchId'], 'qty' => $consumed['qty']], $allocation['consumed']),
            null,
        ];
    }

    /**
     * Mark serial units sold on the sale and take them out of their batches.
     *
     * @param  Collection<int, ProductUnit>  $units
     * @return array{0: float, 1: list<array{batchId: int, qty: int}>, 2: list<array{unitId: int, serialNumber: string, batchId: int}>}
     */
    private function sellUnits(Collection $units, Sale $sale): array
    {
        $taken = $this->stock->takeUnitsFromBatches($units);

        $units->toQuery()->update(['status' => 'sold', 'sale_id' => $sale->id, 'sold_at' => now()]);

        $costCents = $units->sum(fn (ProductUnit $unit): int => SupplierService::cents($unit->cost_price));

        return [
            round($costCents / $units->count() / 100, 2),
            array_map(fn (array $entry): array => ['batchId' => $entry['batch']->id, 'qty' => $entry['qty']], $taken),
            $units->map(fn (ProductUnit $unit): array => [
                'unitId' => $unit->id,
                'serialNumber' => $unit->serial_number,
                'batchId' => $unit->batch_id,
            ])->values()->all(),
        ];
    }

    /**
     * Issue warranties for a line when the product carries one: one per serial unit, otherwise one for the line.
     *
     * @param  ?list<array{unitId: int, serialNumber: string, batchId: int}>  $units
     */
    private function issueWarranties(Sale $sale, Product $product, ?array $units, ?Customer $customer): void
    {
        if ($product->warranty_months <= 0) {
            return;
        }

        $start = now();
        $serials = $units === null ? [null] : array_column($units, 'serialNumber');

        foreach ($serials as $serial) {
            $sale->warranties()->create([
                'customer_id' => $customer?->id,
                'customer_name' => $customer->name ?? self::WALK_IN_CUSTOMER,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'serial_number' => $serial,
                'warranty_months' => $product->warranty_months,
                'start_date' => $start->toDateString(),
                'end_date' => $start->copy()->addMonthsNoOverflow($product->warranty_months)->toDateString(),
                'status' => 'active',
            ]);
        }
    }

    /**
     * Flag products that have just dropped to their low stock level and return them for the alert email.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function flagLowStock(Collection $products): Collection
    {
        return $products
            ->filter(fn (Product $product): bool => $product->isLowOnStock() && ! $product->low_stock_alerted)
            ->each(fn (Product $product) => $product->update(['low_stock_alerted' => true]))
            ->values();
    }

    /**
     * Email the low stock alert to the shop's notify list. A mail failure never undoes the sale.
     *
     * @param  Collection<int, Product>  $products
     */
    private function sendLowStockAlert(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $shop = ShopSetting::current();
        $recipients = array_values(array_filter($shop->notify_emails ?? [], StrictEmail::isValid(...)));

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new LowStockAlertMail($products, $shop));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Points can only be redeemed by a customer who has them.
     *
     * @throws ValidationException
     */
    private function ensurePointsAvailable(int $pointsRedeemed, ?Customer $customer): void
    {
        if ($pointsRedeemed > 0 && ($customer === null || $pointsRedeemed > $customer->loyalty_points)) {
            throw ValidationException::withMessages([
                'points_redeemed' => $customer === null
                    ? 'Select a customer to redeem points.'
                    : "{$customer->name} only has {$customer->loyalty_points} points.",
            ]);
        }
    }

    /**
     * The total the cashier saw must be the total the server works out (prices may have changed since the page loaded).
     *
     * @throws ValidationException
     */
    private function ensureExpectedTotal(int $totalCents, float|int|string $expectedTotal): void
    {
        if (SupplierService::cents($expectedTotal) !== $totalCents) {
            throw ValidationException::withMessages([
                'expected_total' => 'The bill total is now '.Money::format($totalCents / 100).' — please check the cart and try again.',
            ]);
        }
    }

    /**
     * Cash-only bills keep the amount tendered and the change. Nothing tendered means exact cash.
     *
     * @param  array{legs: list<array{method: string, amountCents: int}>, totalCents: int}  $quote
     * @return array{0: ?int, 1: ?int}
     *
     * @throws ValidationException
     */
    private function cashTendered(array $quote, float|int|string|null $amountTendered): array
    {
        if (count($quote['legs']) !== 1 || $quote['legs'][0]['method'] !== 'cash' || blank($amountTendered)) {
            return [null, null];
        }

        $tenderedCents = SupplierService::cents($amountTendered);

        if ($tenderedCents < $quote['totalCents']) {
            throw ValidationException::withMessages(['amount_tendered' => 'The amount tendered is less than the total.']);
        }

        return [$tenderedCents, $tenderedCents - $quote['totalCents']];
    }

    /**
     * The surcharge metadata columns: the % and how much it added to the subtotal (reporting only).
     *
     * @param  array{chargeMethod: ?string, chargePercent: ?float, chargeAmountCents: int}  $quote
     * @return array<string, ?float>
     */
    private function chargeColumns(array $quote): array
    {
        return match ($quote['chargeMethod']) {
            'card' => ['card_charge_percent' => $quote['chargePercent'], 'card_charge_amount' => $quote['chargeAmountCents'] / 100],
            'kokopay' => ['kokopay_charge_percent' => $quote['chargePercent'], 'kokopay_charge_amount' => $quote['chargeAmountCents'] / 100],
            default => [],
        };
    }
}
