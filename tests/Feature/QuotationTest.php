<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
    }

    public function test_a_quotation_is_numbered_and_totalled_on_the_server_without_touching_stock(): void
    {
        $product = Product::factory()->create(['name' => 'Dell 24" Monitor', 'sku' => 'DEL-24', 'selling_price' => 45000]);
        $this->receiveStock($product, 3, 38000, location: 'showroom');
        $stockBefore = $product->fresh()->only(['total_stock', 'stores_stock', 'showroom_stock']);
        $movementsBefore = StockMovement::query()->count();

        $this->store([
            'customer_name' => 'Nimal Silva',
            'customer_phone' => '0771234567',
            'customer_address' => '12 Galle Road, Colombo',
            'items' => [
                ['product_id' => $product->id, 'product_name' => 'ignored', 'qty' => 2, 'unit_price' => 44000, 'discount' => 500],
                ['product_name' => 'Installation & setup', 'qty' => 1, 'unit_price' => 2500.5],
            ],
            'discount_amount' => 1000,
            'note' => 'Prices include delivery.',
            'valid_until' => today()->addDays(14)->toDateString(),
        ])->assertCreated()->assertJsonPath('message', 'QUO-00001 saved.');

        $quotation = Quotation::query()->sole();
        $this->assertSame(
            ['QUO-00001', 'Nimal Silva', '0771234567', '89500.50', '1000.00', '88500.50', 'sent', $this->cashier->id, 'Kasun Perera'],
            [$quotation->quotation_no, $quotation->customer_name, $quotation->customer_phone, $quotation->subtotal,
                $quotation->discount_amount, $quotation->total_amount, $quotation->status, $quotation->prepared_by_id, $quotation->prepared_by_name],
        );

        $items = $quotation->items()->orderBy('id')->get();
        $this->assertSame(['Dell 24" Monitor', 'DEL-24', 2, '44000.00', '500.00', '87000.00'], [
            $items[0]->product_name, $items[0]->sku, $items[0]->qty, $items[0]->unit_price, $items[0]->discount, $items[0]->line_total,
        ]);
        $this->assertSame([null, 'Installation & setup', '2500.50'], [$items[1]->product_id, $items[1]->product_name, $items[1]->line_total]);

        $this->assertSame($stockBefore, $product->fresh()->only(['total_stock', 'stores_stock', 'showroom_stock']));
        $this->assertSame($movementsBefore, StockMovement::query()->count());
    }

    public function test_a_quotation_without_a_customer_is_for_a_walk_in_customer_and_needs_items(): void
    {
        $this->store(['items' => [], 'valid_until' => today()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items' => 'Add at least one item.']);

        $this->store([
            'items' => [['product_name' => 'Laptop service', 'qty' => 1, 'unit_price' => 3000]],
            'valid_until' => today()->subDay()->toDateString(),
        ])->assertJsonValidationErrors(['valid_until' => "The valid until date can't be in the past."]);

        $this->store([
            'items' => [['product_name' => 'Laptop service', 'qty' => 1, 'unit_price' => 3000]],
            'discount_amount' => 3500,
            'valid_until' => today()->toDateString(),
        ])->assertJsonValidationErrors(['discount_amount' => 'The discount must be between 0 and the subtotal.']);

        $this->store([
            'items' => [['product_name' => 'Laptop service', 'qty' => 1, 'unit_price' => 3000]],
            'valid_until' => today()->toDateString(),
        ])->assertCreated();

        $this->assertSame(
            ['customer_name' => 'Walk-in Customer', 'customer_phone' => '', 'customer_address' => '', 'note' => ''],
            Quotation::query()->sole()->only(['customer_name', 'customer_phone', 'customer_address', 'note']),
        );
    }

    public function test_a_quotation_can_be_marked_accepted_or_rejected_and_shows_its_print(): void
    {
        $quotation = $this->createQuotation();

        $this->actingAs($this->cashier)
            ->getJson(route('quotations.show', $quotation))
            ->assertOk()
            ->assertJsonPath('quotation.status_label', 'Sent')
            ->assertSee('id=\"quotation-print\"', false);

        $this->actingAs($this->cashier)
            ->postJson(route('quotations.status', $quotation), ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('message', 'QUO-00001 marked Accepted.')
            ->assertJsonPath('quotation.status', 'accepted');

        $this->actingAs($this->cashier)
            ->postJson(route('quotations.status', $quotation), ['status' => 'accepted'])
            ->assertJsonValidationErrors(['status' => 'QUO-00001 is already Accepted.']);

        $this->actingAs($this->cashier)->postJson(route('quotations.status', $quotation), ['status' => 'rejected'])->assertOk();
        $this->assertSame('rejected', $quotation->fresh()->status);

        $this->actingAs($this->cashier)
            ->postJson(route('quotations.status', $quotation), ['status' => 'converted'])
            ->assertJsonValidationErrors('status');

        $quotation->update(['status' => 'converted']);

        $this->actingAs($this->cashier)
            ->postJson(route('quotations.status', $quotation), ['status' => 'accepted'])
            ->assertJsonValidationErrors(['status' => "QUO-00001 has been converted and can't change."]);
    }

    public function test_a_sent_quotation_past_its_date_shows_and_filters_as_expired(): void
    {
        $current = $this->createQuotation(['customer_name' => 'Current Customer']);
        $lapsed = $this->createQuotation(['customer_name' => 'Lapsed Customer']);
        $lapsed->update(['valid_until' => today()->subDay()]);

        $this->assertSame(['sent', 'expired'], [$current->fresh()->displayStatus(), $lapsed->fresh()->displayStatus()]);

        $this->actingAs($this->cashier)
            ->get(route('quotations.index', ['status' => 'expired']))
            ->assertOk()
            ->assertSee('Lapsed Customer')
            ->assertDontSee('Current Customer');

        $this->actingAs($this->cashier)
            ->get(route('quotations.index', ['status' => 'sent']))
            ->assertSee('Current Customer')
            ->assertDontSee('Lapsed Customer');

        $this->actingAs($this->cashier)
            ->get(route('quotations.index', ['search' => 'Lapsed']))
            ->assertSee('Lapsed Customer')
            ->assertDontSee('Current Customer');
    }

    public function test_deleting_a_quotation_needs_quotations_delete(): void
    {
        $quotation = $this->createQuotation();

        $this->actingAs($this->cashier)->delete(route('quotations.destroy', $quotation))->assertForbidden();
        $this->assertModelExists($quotation);

        $admin = User::factory()->create(['role' => 'Admin']);

        $this->actingAs($admin)
            ->from(route('quotations.index'))
            ->delete(route('quotations.destroy', $quotation))
            ->assertRedirect(route('quotations.index'))
            ->assertSessionHas('status', 'QUO-00001 has been deleted.');

        $this->assertModelMissing($quotation);
        $this->assertDatabaseCount('quotation_items', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createQuotation(array $overrides = []): Quotation
    {
        $this->store([
            'customer_name' => 'Nimal Silva',
            'items' => [['product_name' => 'Laptop service', 'qty' => 1, 'unit_price' => 3000]],
            'valid_until' => today()->addWeek()->toDateString(),
            ...$overrides,
        ])->assertCreated();

        return Quotation::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(array $payload): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson(route('quotations.store'), $payload);
    }
}
