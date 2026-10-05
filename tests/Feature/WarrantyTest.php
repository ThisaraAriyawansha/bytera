<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warranty;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $user;

    private Sale $sale;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(10, 0));

        $this->user = User::factory()->create(['role' => 'Cashier']);
        $this->product = Product::factory()->create(['name' => 'ThinkPad E14', 'warranty_months' => 12, 'low_stock_alert' => 0]);
        $this->receiveStock($this->product, 1, 1000, location: 'showroom');
        app(ShiftService::class)->open($this->user, 0);

        $this->actingAs($this->user)->postJson(route('sales.store'), [
            'items' => [['product_id' => $this->product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash']],
            'expected_total' => (float) $this->product->selling_price,
        ])->assertCreated();

        $this->sale = Sale::query()->sole();
    }

    public function test_the_status_is_computed_from_the_end_date_and_claims(): void
    {
        $active = $this->warranty('Active Customer', '2027-10-04');
        $expiring = $this->warranty('Expiring Customer', '2026-10-15');
        $endsToday = $this->warranty('Today Customer', '2026-10-04');
        $expired = $this->warranty('Expired Customer', '2026-10-03');
        $claimed = $this->warranty('Claimed Customer', '2027-01-01', ['status' => 'claimed', 'claimed_at' => now()]);

        $this->assertSame(['active', 'Active', 'success'], [$active->computedStatus()['key'], $active->computedStatus()['label'], $active->computedStatus()['variant']]);
        $this->assertSame(['Expiring Soon · 11 days', 'warning'], [$expiring->computedStatus()['label'], $expiring->computedStatus()['variant']]);
        $this->assertSame('Expiring Soon · 0 days', $endsToday->computedStatus()['label']);
        $this->assertSame(['Expired', 'danger'], [$expired->computedStatus()['label'], $expired->computedStatus()['variant']]);
        $this->assertSame(['Claimed', 'default'], [$claimed->computedStatus()['label'], $claimed->computedStatus()['variant']]);

        $this->assertSame(
            [
                'active' => ['Active Customer', 'Walk-in Customer'],
                'expiring' => ['Expiring Customer', 'Today Customer'],
                'expired' => ['Expired Customer'],
                'claimed' => ['Claimed Customer'],
            ],
            collect(Warranty::STATUSES)->map(fn (array $meta, string $key): array => Warranty::query()
                ->withComputedStatus($key)->orderBy('customer_name')->pluck('customer_name')->all())->all(),
        );
    }

    public function test_the_page_shows_summary_counts_filters_by_status_and_searches(): void
    {
        $this->warranty('Expired Customer', '2026-09-01');
        $this->warranty('Expiring Customer', '2026-10-20', ['product_name' => 'HP Toner']);

        $this->actingAs($this->user)
            ->get(route('warranty.index'))
            ->assertOk()
            ->assertSee('Walk-in Customer')
            ->assertSee('Expiring Soon · 16 days')
            ->assertSee('Expired Customer');

        $this->actingAs($this->user)
            ->get(route('warranty.index', ['status' => 'expired']))
            ->assertSee('Expired Customer')
            ->assertDontSee('Expiring Customer')
            ->assertViewHas('counts', fn ($counts): bool => $counts->all() === ['active' => 1, 'expiring' => 1, 'expired' => 1, 'claimed' => 0]);

        $this->actingAs($this->user)
            ->get(route('warranty.index', ['search' => 'toner']))
            ->assertSee('Expiring Customer')
            ->assertDontSee('Expired Customer');
    }

    public function test_an_active_warranty_can_be_claimed_with_a_note_once(): void
    {
        $warranty = $this->sale->warranties()->sole();

        $this->actingAs($this->user)
            ->postJson(route('warranty.claim', $warranty), ['claim_note' => ' '])
            ->assertJsonValidationErrors('claim_note');

        $this->actingAs($this->user)
            ->postJson(route('warranty.claim', $warranty), ['claim_note' => 'Screen replaced under warranty'])
            ->assertOk()
            ->assertJsonPath('message', 'Warranty claimed for ThinkPad E14.');

        $warranty->refresh();
        $this->assertSame(['claimed', 'Screen replaced under warranty'], [$warranty->status, $warranty->claim_note]);
        $this->assertTrue($warranty->claimed_at->equalTo(now()));

        $this->actingAs($this->user)
            ->postJson(route('warranty.claim', $warranty), ['claim_note' => 'Again'])
            ->assertJsonValidationErrors(['claim_note' => 'This warranty has already been claimed.']);
    }

    public function test_an_expired_warranty_cannot_be_claimed(): void
    {
        $expired = $this->warranty('Expired Customer', '2026-10-03');

        $this->actingAs($this->user)
            ->postJson(route('warranty.claim', $expired), ['claim_note' => 'Fan replaced'])
            ->assertJsonValidationErrors(['claim_note' => "This warranty expired on Oct 3, 2026 and can't be claimed."]);

        $this->assertSame('active', $expired->fresh()->status);
    }

    public function test_the_warranty_page_needs_warranty_view(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'permissions' => ['warranty.view' => false]]);
        $warranty = $this->sale->warranties()->sole();

        $this->actingAs($user)->get(route('warranty.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('warranty.claim', $warranty), ['claim_note' => 'x'])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function warranty(string $customerName, string $endDate, array $attributes = []): Warranty
    {
        return $this->sale->warranties()->create([
            'customer_name' => $customerName,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'warranty_months' => 12,
            'start_date' => '2025-10-04',
            'end_date' => $endDate,
            'status' => 'active',
            ...$attributes,
        ]);
    }
}
