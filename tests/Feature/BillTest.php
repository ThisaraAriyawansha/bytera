<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warranty;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $cashier;

    private User $admin;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
        $this->admin = User::factory()->create(['role' => 'Admin', 'name' => 'Amaya Fernando']);
        $this->shift = app(ShiftService::class)->open($this->cashier, 5000);
    }

    public function test_reversing_a_bill_puts_every_stock_number_point_balance_and_shift_total_back(): void
    {
        $mouse = Product::factory()->create(['name' => 'USB Mouse', 'selling_price' => 1000, 'low_stock_alert' => 7, 'warranty_months' => 6]);
        $older = $this->receiveStock($mouse, 1, 400, null, now()->subDays(3), location: 'showroom');
        $this->receiveStock($mouse, 5, 500, null, now()->subDays(2), location: 'showroom');
        $this->receiveStock($mouse, 3, 450, null, now()->subDay());

        $laptop = Product::factory()->serialTracked()->create(['name' => 'ThinkPad E14', 'selling_price' => 250000, 'warranty_months' => 12, 'low_stock_alert' => 0]);
        $this->receiveStock($laptop, 3, 200000, null, null, ['TP-1', 'TP-2', 'TP-3'], 'showroom');
        $units = $laptop->units()->whereIn('serial_number', ['TP-1', 'TP-3'])->orderBy('id')->pluck('id')->all();

        $customer = Customer::factory()->create(['name' => 'Nimal Silva', 'loyalty_points' => 50]);

        // An earlier sale in the same shift, which the reversal must leave alone.
        $this->checkout([
            'items' => [['product_id' => $mouse->id, 'qty' => 1]],
            'payments' => [['method' => 'transfer']],
            'expected_total' => 1000,
        ])->assertCreated();

        $before = $this->snapshot($customer);
        $this->assertFalse($mouse->fresh()->low_stock_alerted);

        // 3 mice (FIFO: the older batch went in the first sale, so these come from the newer one) + two laptop
        // serials = 503,000; less a 1,000 bill discount and 30 points = 501,970, split cash 100,000 + card.
        $this->checkout([
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $mouse->id, 'qty' => 3],
                ['product_id' => $laptop->id, 'unit_ids' => $units],
            ],
            'discount_amount' => 1000,
            'points_redeemed' => 30,
            'payments' => [['method' => 'cash', 'amount' => 100000], ['method' => 'card']],
            'expected_total' => 501970,
        ])->assertCreated();

        $sale = Sale::query()->latest('id')->firstOrFail();

        // The sale really changed things.
        $this->assertSame(0, $older->fresh()->remaining_qty);
        $this->assertTrue($mouse->fresh()->low_stock_alerted);
        $this->assertSame(3, $sale->warranties()->count());
        $this->assertNotEquals($before, $this->snapshot($customer));
        $this->assertSame(50 - 30 + 5020, $customer->fresh()->loyalty_points);

        $this->actingAs($this->admin)
            ->postJson(route('bills.reverse', $sale), ['reason' => 'Customer returned the items'])
            ->assertOk()
            ->assertJsonPath('bill.is_cancelled', true)
            ->assertJsonPath('bill.cancel_reason', 'Customer returned the items');

        $this->assertSame($before, $this->snapshot($customer));

        $sale->refresh();
        $this->assertSame(['cancelled', $this->admin->id, 'Amaya Fernando', 'Customer returned the items'], [
            $sale->status, $sale->cancelled_by_id, $sale->cancelled_by_name, $sale->cancel_reason,
        ]);
        $this->assertNotNull($sale->cancelled_at);
        $this->assertSame(0, $sale->warranties()->count());
        $this->assertSame(1, Warranty::query()->count(), 'The earlier sale keeps its warranty.');

        $movements = StockMovement::query()->where('reference_type', 'sale_cancel')->orderBy('id')->get();
        $this->assertSame([[$mouse->id, 'in', 3], [$laptop->id, 'in', 2]], $movements->map(fn (StockMovement $movement): array => [
            $movement->product_id, $movement->type, $movement->qty,
        ])->all());
        $this->assertSame(['showroom'], $movements->pluck('location')->unique()->all());
        $this->assertSame(['Cancelled '.$sale->invoice_no], $movements->pluck('note')->unique()->all());
        $this->assertSame([$sale->id], $movements->pluck('reference_id')->unique()->all());
    }

    public function test_a_bill_can_only_be_reversed_once_and_needs_a_reason(): void
    {
        $sale = $this->sellOne();

        $this->actingAs($this->admin)
            ->postJson(route('bills.reverse', $sale), ['reason' => '  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason' => 'Enter the reason for reversing this bill.']);

        $this->actingAs($this->admin)->postJson(route('bills.reverse', $sale), ['reason' => 'Wrong item'])->assertOk();

        $this->actingAs($this->admin)
            ->postJson(route('bills.reverse', $sale), ['reason' => 'Again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bill' => "{$sale->invoice_no} has already been reversed."]);

        $this->assertSame(1, StockMovement::query()->where('reference_type', 'sale_cancel')->count());
        $this->assertSame(6, $sale->items()->sole()->product->fresh()->showroom_stock);
    }

    public function test_reversing_a_bill_from_a_closed_shift_restores_stock_but_leaves_the_shift_as_counted(): void
    {
        $sale = $this->sellOne();
        app(ShiftService::class)->close($this->shift, $this->cashier, 6000);
        $closed = $this->shift->fresh()->only(['cash_sales_total', 'sales_count', 'expected_cash', 'variance']);

        $this->actingAs($this->admin)->postJson(route('bills.reverse', $sale), ['reason' => 'Refund'])->assertOk();

        $this->assertSame($closed, $this->shift->fresh()->only(['cash_sales_total', 'sales_count', 'expected_cash', 'variance']));
        $this->assertSame(6, $sale->items()->sole()->product->fresh()->showroom_stock);
    }

    public function test_reversing_and_editing_need_their_permissions(): void
    {
        $sale = $this->sellOne();

        $this->actingAs($this->cashier)->postJson(route('bills.reverse', $sale), ['reason' => 'Refund'])->assertForbidden();
        $this->actingAs($this->cashier)->putJson(route('bills.update', $sale), $this->editPayload($sale))->assertForbidden();
        $this->actingAs($this->cashier)->getJson(route('bills.show', $sale))->assertOk();

        $this->assertNull($sale->fresh()->status);
    }

    public function test_edit_bill_changes_only_the_allowed_fields_and_writes_an_audit_log(): void
    {
        $sale = $this->sellOne();

        $this->actingAs($this->admin)
            ->putJson(route('bills.update', $sale), [
                ...$this->editPayload($sale),
                'customer_name' => 'Saman Kumara',
                'customer_phone' => '0771234567',
                'customer_email' => 'Saman@Example.com',
                'note' => 'Delivered to office',
                'total_amount' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('message', "{$sale->invoice_no} updated.")
            ->assertJsonPath('bill.customer_email', 'saman@example.com');

        $sale->refresh();
        $this->assertSame(['Saman Kumara', '0771234567', 'saman@example.com', 'Delivered to office', '1000.00'], [
            $sale->customer_name, $sale->customer_phone, $sale->customer_email, $sale->note, $sale->total_amount,
        ]);

        $log = AuditLog::query()->sole();
        $this->assertSame(['sales', (string) $sale->id, $sale->invoice_no, $this->admin->id], [
            $log->collection_name, $log->doc_id, $log->label, $log->performed_by_id,
        ]);
        $this->assertSame(['customer_name', 'customer_phone', 'customer_email', 'note'], array_column($log->changes, 'field'));
        $this->assertSame(['field' => 'customer_name', 'before' => 'Walk-in Customer', 'after' => 'Saman Kumara'], $log->changes[0]);

        $this->actingAs($this->admin)
            ->putJson(route('bills.update', $sale), $this->editPayload($sale->fresh()))
            ->assertOk()
            ->assertJsonPath('message', 'Nothing changed.');

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_changing_the_payment_method_moves_the_amount_between_open_shift_totals(): void
    {
        $sale = $this->sellOne();

        $this->actingAs($this->admin)
            ->putJson(route('bills.update', $sale), [...$this->editPayload($sale), 'payment_method' => 'card'])
            ->assertOk();

        $shift = $this->shift->fresh();
        $this->assertSame(['0.00', '1000.00', 1], [$shift->cash_sales_total, $shift->card_sales_total, $shift->sales_count]);
        $this->assertSame('card', $sale->fresh()->payment_method);

        // Reversing afterwards takes it off the card total it now sits in.
        $this->actingAs($this->admin)->postJson(route('bills.reverse', $sale), ['reason' => 'Refund'])->assertOk();

        $shift->refresh();
        $this->assertSame(['0.00', '0.00', 0], [$shift->cash_sales_total, $shift->card_sales_total, $shift->sales_count]);
    }

    public function test_a_split_bills_payment_method_cannot_be_changed(): void
    {
        $product = $this->showroomProduct();

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 400], ['method' => 'transfer', 'amount' => 600]],
            'expected_total' => 1000,
        ])->assertCreated();

        $sale = Sale::query()->sole();

        $this->actingAs($this->admin)
            ->putJson(route('bills.update', $sale), [...$this->editPayload($sale), 'payment_method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method' => "This bill was paid Cash + Transfer — a split payment's method can't be changed."]);
    }

    public function test_a_cancelled_bill_cannot_be_edited(): void
    {
        $sale = $this->sellOne();
        $this->actingAs($this->admin)->postJson(route('bills.reverse', $sale), ['reason' => 'Refund'])->assertOk();

        $this->actingAs($this->admin)
            ->putJson(route('bills.update', $sale), [...$this->editPayload($sale), 'customer_name' => 'Someone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['bill' => "{$sale->invoice_no} has been reversed and can't be edited."]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_bills_list_filters_by_date_and_search_and_marks_cancelled_bills(): void
    {
        $kept = $this->sellOne();
        $cancelled = $this->sellOne();
        $this->actingAs($this->admin)->postJson(route('bills.reverse', $cancelled), ['reason' => 'Refund'])->assertOk();

        $this->travel(-40)->days();
        $old = $this->sellOne();
        $this->travelBack();

        $this->actingAs($this->cashier)
            ->get(route('bills.index'))
            ->assertOk()
            ->assertSee($kept->invoice_no)
            ->assertSee($cancelled->invoice_no)
            ->assertDontSee($old->invoice_no)
            ->assertSee('Cancelled');

        $this->actingAs($this->cashier)
            ->get(route('bills.index', ['search' => $kept->invoice_no]))
            ->assertSee($kept->invoice_no)
            ->assertDontSee($cancelled->invoice_no);

        $this->actingAs($this->cashier)
            ->getJson(route('bills.show', $cancelled))
            ->assertOk()
            ->assertJsonPath('bill.is_cancelled', true)
            ->assertJsonPath('bill.urls.email', null)
            ->assertSee('CANCELLED');
    }

    public function test_the_bills_page_is_restricted_without_bills_view(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'permissions' => ['bills.view' => false]]);

        $this->actingAs($user)->get(route('bills.index'))->assertForbidden()->assertSee('Access Restricted');
    }

    /**
     * Every number a sale changes and a reversal must restore.
     *
     * @return array<string, mixed>
     */
    private function snapshot(Customer $customer): array
    {
        return [
            'products' => Product::query()->orderBy('id')->get()
                ->map(fn (Product $product): array => $product->only(['id', 'total_stock', 'stores_stock', 'showroom_stock', 'low_stock_alerted']))
                ->all(),
            'batches' => ProductBatch::query()->orderBy('id')->get()
                ->map(fn (ProductBatch $batch): array => $batch->only(['id', 'location', 'total_qty', 'remaining_qty', 'status']))
                ->all(),
            'units' => ProductUnit::query()->orderBy('id')->get()
                ->map(fn (ProductUnit $unit): array => [...$unit->only(['id', 'status', 'location', 'sale_id']), 'sold_at' => $unit->sold_at?->toIso8601String()])
                ->all(),
            'points' => $customer->fresh()->loyalty_points,
            'shift' => $this->shift->fresh()->only([
                'sales_count', 'cash_sales_total', 'card_sales_total', 'transfer_sales_total', 'kokopay_sales_total', 'status',
            ]),
        ];
    }

    private function showroomProduct(): Product
    {
        $product = Product::factory()->create(['selling_price' => 1000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 6, 600, location: 'showroom');

        return $product;
    }

    private function sellOne(): Sale
    {
        $product = $this->showroomProduct();

        $this->checkout([
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash']],
            'expected_total' => 1000,
        ])->assertCreated();

        return Sale::query()->latest('id')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayload(Sale $sale): array
    {
        return $sale->only(['customer_name', 'customer_phone', 'customer_email', 'note', 'payment_method']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function checkout(array $payload): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson(route('sales.store'), $payload);
    }
}
