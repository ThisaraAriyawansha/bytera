<?php

namespace Tests\Feature;

use App\Mail\LowStockAlertMail;
use App\Mail\SaleReceiptMail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Shift;
use App\Models\ShopSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $cashier;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
        $this->shift = app(ShiftService::class)->open($this->cashier, 5000, 'Morning shift');
    }

    public function test_a_cash_sale_consumes_showroom_batches_fifo_and_records_tendered_and_change(): void
    {
        $product = Product::factory()->create(['name' => 'USB Mouse', 'selling_price' => 1000, 'low_stock_alert' => 0]);
        $older = $this->receiveStock($product, 1, 400, null, now()->subDays(2), location: 'showroom');
        $newer = $this->receiveStock($product, 5, 500, null, now()->subDay(), location: 'showroom');

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [['method' => 'cash']],
            'amount_tendered' => 2500,
            'expected_total' => 2000,
        ])->assertCreated()->assertJsonPath('sale.invoice_no', 'INV-00001');

        $sale = Sale::query()->sole();
        $this->assertSame(['2000.00', '2000.00', 'cash', null, '2500.00', '500.00', 'paid', 'Walk-in Customer', $this->shift->id], [
            $sale->subtotal, $sale->total_amount, $sale->payment_method, $sale->payments,
            $sale->amount_tendered, $sale->change_amount, $sale->payment_status, $sale->customer_name, $sale->shift_id,
        ]);

        $item = $sale->items()->sole();
        $this->assertSame(['1000.00', '450.00', '2000.00'], [$item->unit_price, $item->cost_price, $item->line_total]);
        $this->assertSame([['batchId' => $older->id, 'qty' => 1], ['batchId' => $newer->id, 'qty' => 1]], $item->batch_allocations);
        $this->assertSame([0, 'depleted', 4], [$older->fresh()->remaining_qty, $older->fresh()->status, $newer->fresh()->remaining_qty]);

        $product->refresh();
        $this->assertSame([4, 4, 0], [$product->total_stock, $product->showroom_stock, $product->stores_stock]);

        $movement = StockMovement::query()->where('reference_type', 'sale')->sole();
        $this->assertSame(['out', -2, 'showroom', 'Sale INV-00001', $sale->id], [$movement->type, $movement->qty, $movement->location, $movement->note, $movement->reference_id]);

        $shift = $this->shift->fresh();
        $this->assertSame([1, '2000.00', '0.00'], [$shift->sales_count, $shift->cash_sales_total, $shift->card_sales_total]);
    }

    public function test_a_cash_and_card_split_records_each_leg_and_the_largest_as_the_method(): void
    {
        $product = Product::factory()->create(['selling_price' => 3000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 3, 2000, location: 'showroom');

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 1000], ['method' => 'card', 'amount' => 2000]],
            'expected_total' => 3000,
        ])->assertCreated();

        $sale = Sale::query()->sole();
        $this->assertSame('card', $sale->payment_method);
        $this->assertSame([['method' => 'cash', 'amount' => 1000], ['method' => 'card', 'amount' => 2000]], $sale->payments);
        $this->assertNull($sale->card_charge_percent);
        $this->assertNull($sale->amount_tendered);

        $shift = $this->shift->fresh();
        $this->assertSame(['1000.00', '2000.00', 1], [$shift->cash_sales_total, $shift->card_sales_total, $shift->sales_count]);
    }

    public function test_a_card_surcharge_on_its_own_raises_every_item_price_by_the_percent(): void
    {
        $product = Product::factory()->create(['selling_price' => 1999, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 2, 1500, location: 'showroom');
        $service = Service::factory()->create(['name' => 'Laptop cleaning', 'default_price' => 500]);

        // 1999 × 1.03 = 2058.97 → 2059; 500 × 1.03 = 515.
        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'services' => [['service_id' => $service->id, 'price' => 500]],
            'payments' => [['method' => 'card']],
            'card_charge_percent' => 3,
            'expected_total' => 2574,
        ])->assertCreated();

        $sale = Sale::query()->sole();
        $this->assertSame(['2574.00', '2574.00', 'card', '3.00', '75.00'], [
            $sale->subtotal, $sale->total_amount, $sale->payment_method, $sale->card_charge_percent, $sale->card_charge_amount,
        ]);
        $this->assertSame('2059.00', $sale->items()->sole()->unit_price);
        $this->assertSame([515, 500], [$sale->services[0]['price'], $sale->services[0]['basePrice']]);
        $this->assertSame('2574.00', $this->shift->fresh()->card_sales_total);
    }

    public function test_a_card_surcharge_in_a_split_only_applies_to_the_card_portion(): void
    {
        $laptop = Product::factory()->create(['selling_price' => 8000, 'low_stock_alert' => 0]);
        $bag = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($laptop, 1, 6000, location: 'showroom');
        $this->receiveStock($bag, 1, 1000, location: 'showroom');

        // base 10,000 − discount 500 − cash 4,000 = card base 5,500; fee = 3% = 165;
        // multiplier 1 + 165/10,000 → 8,132 + 2,033 = 10,165; total 9,665; card leg 9,665 − 4,000 = 5,665.
        $payload = [
            'items' => [['product_id' => $laptop->id, 'qty' => 1], ['product_id' => $bag->id, 'qty' => 1]],
            'discount_amount' => 500,
            'payments' => [['method' => 'cash', 'amount' => 4000], ['method' => 'card', 'amount' => 1]],
            'card_charge_percent' => 3,
            'expected_total' => 9665,
        ];

        $this->checkout([...$payload, 'expected_total' => 9500])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_total' => 'The bill total is now Rs. 9,665 — please check the cart and try again.']);

        $this->checkout($payload)->assertCreated();

        $sale = Sale::query()->sole();
        $this->assertSame(['10165.00', '500.00', '9665.00', '3.00', '165.00'], [
            $sale->subtotal, $sale->discount_amount, $sale->total_amount, $sale->card_charge_percent, $sale->card_charge_amount,
        ]);
        $this->assertSame(['8132.00', '2033.00'], $sale->items()->orderBy('id')->pluck('unit_price')->all());
        $this->assertSame([['method' => 'cash', 'amount' => 4000], ['method' => 'card', 'amount' => 5665]], $sale->payments);
        $this->assertSame('card', $sale->payment_method);

        $shift = $this->shift->fresh();
        $this->assertSame(['4000.00', '5665.00'], [$shift->cash_sales_total, $shift->card_sales_total]);
    }

    public function test_kokopay_raises_prices_by_its_percent_and_cannot_be_split(): void
    {
        $product = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 5, 1200, location: 'showroom');

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 2]],
            'payments' => [['method' => 'kokopay', 'amount' => 1000], ['method' => 'cash', 'amount' => 3000]],
            'kokopay_charge_percent' => 5,
            'expected_total' => 4000,
        ])->assertJsonValidationErrors(['payments' => "KokoPay can't be combined with other payment methods."]);

        // 2000 × 1.05 = 2100 a unit, less Rs. 100 discount a unit, × 2 = 4000 (base 3800).
        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 2, 'discount' => 100]],
            'payments' => [['method' => 'kokopay']],
            'kokopay_charge_percent' => 5,
            'expected_total' => 4000,
        ])->assertCreated();

        $sale = Sale::query()->sole();
        $this->assertSame(['4000.00', 'kokopay', '5.00', '200.00', null], [
            $sale->total_amount, $sale->payment_method, $sale->kokopay_charge_percent, $sale->kokopay_charge_amount, $sale->card_charge_percent,
        ]);
        $item = $sale->items()->sole();
        $this->assertSame(['2100.00', '100.00', '4000.00'], [$item->unit_price, $item->discount, $item->line_total]);
        $this->assertSame('4000.00', $this->shift->fresh()->kokopay_sales_total);
    }

    public function test_selling_more_than_the_showroom_holds_is_refused_and_points_to_stores(): void
    {
        $product = Product::factory()->create(['name' => 'HDMI Cable', 'selling_price' => 800]);
        $this->receiveStock($product, 1, 300, location: 'showroom');
        $this->receiveStock($product, 2, 300);

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 3]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 2400,
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'stock' => 'Not enough Showroom stock for "HDMI Cable": requested 3, only 1 in Showroom (2 more in Stores — transfer to Showroom first.)',
        ]);

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame([1, 2], [$product->fresh()->showroom_stock, $product->fresh()->stores_stock]);
        $this->assertSame(0, $this->shift->fresh()->sales_count);
    }

    public function test_a_serial_product_sale_marks_units_sold_and_issues_one_warranty_per_serial(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(10, 0));

        $product = Product::factory()->serialTracked()->create(['name' => 'ThinkPad E14', 'selling_price' => 250000, 'warranty_months' => 12, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 3, 200000, null, null, ['TP-1', 'TP-2', 'TP-3'], 'showroom');
        $units = $product->units()->whereIn('serial_number', ['TP-1', 'TP-3'])->orderBy('id')->get();
        $customer = Customer::factory()->create(['name' => 'Nimal Silva']);

        $this->checkout([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'unit_ids' => $units->pluck('id')->all()]],
            'payments' => [['method' => 'transfer']],
            'expected_total' => 500000,
        ])->assertCreated();

        $sale = Sale::query()->sole();
        $item = $sale->items()->sole();
        $this->assertSame(2, $item->qty);
        $this->assertSame(['TP-1', 'TP-3'], array_column($item->units, 'serialNumber'));

        $this->assertSame(['sold'], $units->fresh()->pluck('status')->unique()->all());
        $this->assertSame([$sale->id], $units->fresh()->pluck('sale_id')->unique()->all());
        $this->assertSame(['TP-2'], $product->units()->where('status', 'in_stock')->pluck('serial_number')->all());
        $this->assertSame(1, $product->fresh()->showroom_stock);

        $warranties = $sale->warranties()->orderBy('serial_number')->get();
        $this->assertSame(['TP-1', 'TP-3'], $warranties->pluck('serial_number')->all());
        $this->assertSame(['Nimal Silva'], $warranties->pluck('customer_name')->unique()->all());
        $this->assertSame(['2026-10-04', '2027-10-04', 12, 'active'], [
            $warranties[0]->start_date->toDateString(), $warranties[0]->end_date->toDateString(), $warranties[0]->warranty_months, $warranties[0]->status,
        ]);

        $this->checkout([
            'items' => [['product_id' => $product->id, 'unit_ids' => [$units[0]->id]]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 250000,
        ])->assertJsonValidationErrors(['items.0' => '"TP-1" is no longer available for sale']);
    }

    public function test_loyalty_points_are_earned_on_the_discounted_subtotal_and_redeemed_as_rupees(): void
    {
        $product = Product::factory()->create(['selling_price' => 12345, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 2, 9000, location: 'showroom');
        $customer = Customer::factory()->create(['loyalty_points' => 50]);

        $this->checkout([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'points_redeemed' => 51,
            'payments' => [['method' => 'cash']],
            'expected_total' => 12294,
        ])->assertJsonValidationErrors(['points_redeemed' => "{$customer->name} only has 50 points."]);

        // Earned = floor((12,345 − 345) / 100) = 120; balance 50 − 50 + 120 = 120.
        $this->checkout([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'discount_amount' => 345,
            'points_redeemed' => 50,
            'payments' => [['method' => 'cash']],
            'expected_total' => 11950,
        ])->assertCreated()->assertJsonPath('customer.loyalty_points', 120);

        $sale = Sale::query()->sole();
        $this->assertSame(['11950.00', 50, $customer->id], [$sale->total_amount, $sale->points_redeemed, $sale->customer_id]);
        $this->assertSame(120, $customer->fresh()->loyalty_points);
    }

    public function test_service_custom_fields_are_validated_and_stored_on_the_sale(): void
    {
        $service = Service::factory()->create([
            'name' => 'Screen Replacement',
            'default_price' => 15000,
            'custom_fields' => [
                ['id' => 'model', 'label' => 'Model Number', 'type' => 'text', 'required' => true, 'placeholder' => '', 'options' => []],
                ['id' => 'backup', 'label' => 'Data backed up', 'type' => 'checkbox', 'required' => true, 'placeholder' => '', 'options' => []],
            ],
        ]);

        $payload = fn (array $fields): array => [
            'services' => [['service_id' => $service->id, 'price' => 14000, 'fields' => $fields]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 14000,
        ];

        $this->checkout($payload(['model' => ' ', 'backup' => true]))->assertJsonValidationErrors(['services.0.fields.model' => '"Model Number" is required.']);
        $this->checkout($payload(['model' => 'X1', 'backup' => false]))->assertJsonValidationErrors(['services.0.fields.backup' => '"Data backed up" must be ticked.']);
        $this->checkout($payload(['model' => 'X1', 'backup' => true]))->assertCreated();

        $line = Sale::query()->sole()->services[0];
        $this->assertSame(['Screen Replacement', 14000, 'paid'], [$line['name'], $line['price'], $line['chargeType']]);
        $this->assertSame([['label' => 'Model Number', 'type' => 'text', 'value' => 'X1'], ['label' => 'Data backed up', 'type' => 'checkbox', 'value' => true]], $line['fields']);
    }

    public function test_a_sale_needs_the_cashiers_own_open_shift(): void
    {
        $product = Product::factory()->create(['selling_price' => 100]);
        $this->receiveStock($product, 1, 50, location: 'showroom');

        $this->actingAs(User::factory()->create(['role' => 'Cashier']))
            ->postJson(route('sales.store'), [
                'items' => [['product_id' => $product->id, 'qty' => 1]],
                'payments' => [['method' => 'cash']],
                'expected_total' => 100,
            ])
            ->assertJsonValidationErrors(['shift' => 'Your shift is not open — reopen a shift before selling.']);
    }

    public function test_split_legs_must_balance_when_there_is_no_card(): void
    {
        $product = Product::factory()->create(['selling_price' => 3000]);
        $this->receiveStock($product, 1, 1000, location: 'showroom');

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 1000], ['method' => 'transfer', 'amount' => 1500]],
            'expected_total' => 3000,
        ])->assertJsonValidationErrors(['payments' => "The payments don't cover the total — Remaining: Rs. 500."]);
    }

    public function test_crossing_the_low_stock_level_emails_the_notify_list_once(): void
    {
        Mail::fake();
        ShopSetting::query()->update(['notify_emails' => ['owner@example.com', 'bad address']]);

        $product = Product::factory()->create(['selling_price' => 500, 'low_stock_alert' => 5]);
        $this->receiveStock($product, 7, 200, location: 'showroom');
        $sellOne = fn () => $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 500,
        ])->assertCreated();

        $sellOne();
        Mail::assertNothingSent();

        $sellOne();
        $this->assertTrue($product->fresh()->low_stock_alerted);
        Mail::assertSent(LowStockAlertMail::class, fn (LowStockAlertMail $mail): bool => $mail->hasTo('owner@example.com')
            && ! $mail->hasTo('bad address')
            && $mail->products->pluck('id')->all() === [$product->id]);

        $sellOne();
        Mail::assertSentCount(1);
    }

    public function test_the_bill_is_emailed_to_the_customer_on_the_sale_with_the_pdf_attached(): void
    {
        Mail::fake();

        $product = Product::factory()->create(['selling_price' => 500, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 2, 200, location: 'showroom');
        $customer = Customer::factory()->create(['email' => 'nimal@example.com']);

        $response = $this->checkout([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 500,
        ])->assertCreated();

        $this->assertStringContainsString('id="bill-print"', $response->json('billHtml'));
        $this->assertStringContainsString('Warranty replacement period: 14 days', $response->json('billHtml'));

        $this->actingAs($this->cashier)
            ->postJson($response->json('sale.emailUrl'), ['pdf' => base64_encode('not a pdf')])
            ->assertJsonValidationErrors(['pdf' => 'The bill PDF could not be read. Please try again.']);

        $this->actingAs($this->cashier)
            ->postJson($response->json('sale.emailUrl'), ['pdf' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 test')])
            ->assertOk()
            ->assertJsonPath('message', 'Bill emailed to nimal@example.com.');

        Mail::assertSent(SaleReceiptMail::class, function (SaleReceiptMail $mail): bool {
            $mail->assertHasAttachedData('%PDF-1.4 test', 'INV-00001.pdf', ['mime' => 'application/pdf']);
            $mail->assertSeeInText('Total:    Rs. 500');

            return $mail->hasTo('nimal@example.com') && $mail->hasSubject('Your receipt INV-00001 - M-Fixpro');
        });
    }

    public function test_the_pos_page_lists_only_active_products_with_showroom_stock(): void
    {
        $inShowroom = Product::factory()->create(['name' => 'Showroom Keyboard']);
        $this->receiveStock($inShowroom, 2, 100, location: 'showroom');
        $storesOnly = Product::factory()->create(['name' => 'Stores Only Router']);
        $this->receiveStock($storesOnly, 2, 100);
        $inactive = Product::factory()->create(['name' => 'Retired Webcam', 'active' => false]);
        $this->receiveStock($inactive, 2, 100, location: 'showroom');

        $this->actingAs($this->cashier)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Showroom Keyboard')
            ->assertDontSee('Stores Only Router')
            ->assertDontSee('Retired Webcam')
            ->assertSee($this->shift->shift_no);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkout(array $payload): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson(route('sales.store'), $payload);
    }
}
