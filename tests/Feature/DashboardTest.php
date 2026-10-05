<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStatusHistory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(CarbonImmutable::today()->setTime(18, 0));

        $this->admin = User::factory()->admin()->create(['name' => 'Amaya Fernando']);
        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
        app(ShiftService::class)->open($this->cashier, 5000);
    }

    public function test_the_dashboard_figures_match_the_finance_overview_for_every_period(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 10, 1200, location: 'showroom');

        $this->checkout($product, [['method' => 'cash']], ['customer_id' => $customer->id])->assertCreated();
        $this->checkout($product, [['method' => 'cash', 'amount' => 500], ['method' => 'card']])->assertCreated();
        $this->checkout($product, [['method' => 'transfer']])->assertCreated();
        Sale::query()->latest('id')->firstOrFail()->update(['status' => 'cancelled']);
        $this->checkout($product, [['method' => 'card']])->assertCreated();
        $this->backdateLatestSale(now()->subDays(10));
        $this->actingAs($this->admin)->postJson(route('finance.expenses.store'), ['category' => 'rent', 'amount' => 1000])->assertCreated();

        foreach (array_keys(DashboardService::PERIODS) as $period) {
            $dashboard = $this->dashboard($this->admin, $period);
            $range = $dashboard['range'];
            $overview = $this->actingAs($this->admin)
                ->get(route('finance.index', ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString()]))
                ->viewData('overview');

            $this->assertSame(
                [$overview['revenue'], $overview['cogs'], $overview['gross_profit'], $overview['expenses'], $overview['net_profit'], $overview['sales_count']],
                [
                    $dashboard['revenue']['amount'],
                    $dashboard['profit']['bars'][1]['amount'],
                    $dashboard['profit']['bars'][2]['amount'],
                    $dashboard['profit']['bars'][3]['amount'],
                    $dashboard['profit']['net_profit'],
                    $dashboard['revenue']['orders'],
                ],
                "Dashboard and Finance disagree for {$period}.",
            );
            $this->assertSame($overview['payment_methods'], $dashboard['paymentMethods']);
            $this->assertSame(array_column($overview['cashiers'], 'revenue'), array_column($dashboard['team'], 'revenue'));
        }

        // Today: the cancelled transfer sale and the 10-day-old card sale are left out; the split counts per leg.
        $today = $this->dashboard($this->admin, 'today');
        $this->assertEquals([4000, 2, 2, 2000], [$today['revenue']['amount'], $today['revenue']['orders'], $today['revenue']['items_sold'], $today['revenue']['average_ticket']]);
        $this->assertEquals(
            ['cash' => 2500.0, 'card' => 1500.0, 'transfer' => 0.0, 'kokopay' => 0.0],
            array_column($today['paymentMethods'], 'amount', 'method'),
        );
        $this->assertEquals([['Kasun Perera', 'KP', 2, 4000]], array_map(
            fn (array $member): array => [$member['cashier_name'], $member['initials'], $member['sales_count'], $member['revenue']],
            $today['team'],
        ));
        $this->assertEquals([2000, 1, 1], [$today['topCustomers']['customers'][0]['spend'], $today['topCustomers']['customers'][0]['visits'], $today['topCustomers']['walk_ins']]);
        $this->assertEquals([2, 4000, 40], [$today['topProducts'][0]['qty'], $today['topProducts'][0]['revenue'], $today['topProducts'][0]['margin']]);
        $this->assertSame([[$product->mainCategory->name, 100.0]], array_map(fn (array $segment): array => [$segment['name'], $segment['percent']], $today['salesMix']['segments']));

        $this->assertEquals(6000, $this->dashboard($this->admin, '30d')['revenue']['amount']);
    }

    public function test_the_trend_compares_each_hour_or_day_with_the_previous_period(): void
    {
        $product = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 10, 1200, location: 'showroom');

        $this->checkout($product, [['method' => 'cash']])->assertCreated();
        $this->backdateLatestSale(now()->setTime(10, 15));
        $this->checkout($product, [['method' => 'cash']])->assertCreated();
        $this->backdateLatestSale(now()->subDay()->setTime(15, 30));

        $today = $this->dashboard($this->admin, 'today')['revenue'];
        $this->assertCount(24, $today['trend']);
        $this->assertSame([2000.0, 0.0], [$today['trend'][10]['current'], $today['trend'][10]['previous']]);
        $this->assertSame([0.0, 2000.0], [$today['trend'][15]['current'], $today['trend'][15]['previous']]);
        $this->assertSame(0.0, $today['delta']);

        $month = $this->dashboard($this->admin, '30d');
        $this->assertCount(30, $month['revenue']['trend']);
        $this->assertSame([2000.0, 2000.0], [$month['revenue']['trend'][28]['current'], $month['revenue']['trend'][29]['current']]);
        $this->assertNull($month['revenue']['delta'], 'Nothing sold in the previous 30 days, so there is no % to show.');

        $this->assertSame([10, 11, 12, 13, 14, 15], $month['heatmap']['hours']);
        $this->assertSame(2, $month['heatmap']['total']);
        $this->assertSame(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], array_column($month['heatmap']['rows'], 'day'));
    }

    public function test_the_workshop_attention_strip_and_low_stock_reflect_the_jobs_and_products(): void
    {
        Job::factory()->create(['status' => 'pending', 'expected_delivery_date' => now()->subDay()->toDateString()]);
        Job::factory()->create(['status' => 'ongoing', 'assigned_technician_name' => 'Ruwan Silva', 'device_type' => 'Printer']);
        $ready = Job::factory()->create(['status' => 'done', 'job_no' => 'JOB-00042', 'customer_name' => 'Nimal Perera']);
        JobStatusHistory::query()->create(['job_id' => $ready->id, 'status' => 'done', 'note' => 'Fixed', 'updated_by_id' => $this->admin->id, 'updated_by_name' => 'Amaya'])
            ->forceFill(['created_at' => now()->subDays(9)])
            ->save();
        $delivered = Job::factory()->create(['status' => 'delivered', 'date_returned' => now()]);
        $delivered->forceFill(['created_at' => now()->subDays(3)])->save();
        Job::factory()->create(['status' => 'unrepairable']);

        Product::factory()->create(['name' => 'HP Toner', 'total_stock' => 0, 'low_stock_alert' => 5]);
        Product::factory()->create(['total_stock' => 0, 'low_stock_alert' => 5, 'active' => false]);
        Product::factory()->create(['total_stock' => 10, 'stores_stock' => 10, 'low_stock_alert' => 5]);

        $response = $this->actingAs($this->admin)->get(route('dashboard', ['period' => '7d']))->assertOk();
        $dashboard = $response->viewData('dashboard');

        $this->assertSame(['overdue_repairs' => 1, 'ready_for_pickup' => 1, 'low_stock' => 1, 'unsettled_bills' => 0], $dashboard['attention']);
        $this->assertSame(
            ['pending' => 1, 'ongoing' => 1, 'done' => 1, 'delivered' => 1, 'unrepairable' => 1],
            array_column($dashboard['workshop']['pipeline'], 'count', 'status'),
        );
        $this->assertSame([3.0, 1, 50.0], [$dashboard['workshop']['average_turnaround'], $dashboard['workshop']['delivered'], $dashboard['workshop']['fix_rate']]);
        $this->assertSame([['JOB-00042', 'Nimal Perera', 9]], array_map(fn (array $job): array => [$job['job_no'], $job['customer_name'], $job['days']], $dashboard['workshop']['waiting']));
        $this->assertEqualsCanonicalizing(['Ruwan Silva', 'Unassigned'], array_column($dashboard['workshop']['technicians'], 'name'));
        $this->assertSame(['2', '1 overdue'], [$dashboard['kpis'][0]['value'], $dashboard['kpis'][0]['hint']]);
        $this->assertSame(['HP Toner'], array_column($dashboard['lowStock']['products'], 'name'));
        $this->assertSame(2, $dashboard['lowStock']['tracked']);

        $response->assertSee('1 repair past promised date')
            ->assertSee('1 device ready for pickup')
            ->assertSee('1 product low on stock')
            ->assertSee('Repair Workshop')
            ->assertSee('0 left');
    }

    public function test_sections_follow_the_users_permissions(): void
    {
        $product = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 10, 1200, location: 'showroom');
        $this->checkout($product, [['method' => 'cash']])->assertCreated();
        Sale::query()->latest('id')->firstOrFail()->update(['payment_status' => 'partial']);

        $admin = $this->dashboard($this->admin, 'today');
        $this->assertSame(1, $admin['attention']['unsettled_bills']);
        $this->assertSame(['Active repairs', 'Jobs received', 'New customers', 'Unsettled bills'], array_column($admin['kpis'], 'label'));

        $noFinance = User::factory()->create(['role' => 'Cashier', 'permissions' => ['finance.view' => false]]);
        $cashier = $this->actingAs($noFinance)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Business Overview')
            ->assertSee('Repair Workshop')
            ->assertDontSee('Profit & Loss')
            ->assertDontSee('Top Products')
            ->assertDontSee('Top Customers')
            ->viewData('dashboard');

        $this->assertNull($cashier['revenue']);
        $this->assertNull($cashier['profit']);
        $this->assertNull($cashier['topProducts']);
        $this->assertNull($cashier['topCustomers']);
        $this->assertSame(0, $cashier['attention']['unsettled_bills']);
        $this->assertSame(['Orders', 'Products', 'Customers'], array_column($cashier['kpis'], 'label'));

        $technician = User::factory()->create(['role' => 'Technician', 'permissions' => ['jobs.view' => false]]);
        $this->assertNull($this->dashboard($technician, 'today')['workshop']);

        $blocked = User::factory()->create(['role' => 'Staff', 'permissions' => ['dashboard.view' => false]]);
        $this->actingAs($blocked)->get(route('dashboard'))->assertForbidden()->assertSee('Access Restricted');
    }

    public function test_an_unknown_period_falls_back_to_today_and_the_helpers_format_changes(): void
    {
        $this->assertSame('today', $this->dashboard($this->admin, 'yearly')['period']);

        $ranges = DashboardService::ranges('7d', CarbonImmutable::parse('2026-10-05'));
        $this->assertSame(['2026-09-29 00:00:00', '2026-10-05 23:59:59', '2026-09-22 00:00:00', '2026-09-28 23:59:59'], [
            $ranges['current']['from']->toDateTimeString(), $ranges['current']['to']->toDateTimeString(),
            $ranges['previous']['from']->toDateTimeString(), $ranges['previous']['to']->toDateTimeString(),
        ]);

        $this->assertSame(25.0, DashboardService::delta(1250, 1000));
        $this->assertSame(-50.0, DashboardService::delta(500, 1000));
        $this->assertSame(50.0, DashboardService::delta(-200, -400));
        $this->assertNull(DashboardService::delta(10, 0));

        $now = CarbonImmutable::parse('2026-10-05 12:00:00');
        $this->assertSame(
            ['just now', '5m ago', '3h ago', '2d ago'],
            array_map(fn (string $time): string => DashboardService::ago(CarbonImmutable::parse($time), $now), ['2026-10-05 11:59:30', '2026-10-05 11:55:00', '2026-10-05 09:00:00', '2026-10-03 11:00:00']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboard(User $user, string $period): array
    {
        return $this->actingAs($user)->get(route('dashboard', ['period' => $period]))->assertOk()->viewData('dashboard');
    }

    /**
     * @param  list<array{method: string, amount?: int}>  $payments
     * @param  array<string, mixed>  $extra
     */
    private function checkout(Product $product, array $payments, array $extra = []): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson(route('sales.store'), [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => $payments,
            'expected_total' => (float) $product->selling_price,
            ...$extra,
        ]);
    }

    private function backdateLatestSale(mixed $at): void
    {
        Sale::query()->latest('id')->firstOrFail()->forceFill(['created_at' => $at])->save();
    }
}
