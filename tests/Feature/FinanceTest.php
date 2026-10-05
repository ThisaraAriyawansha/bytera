<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private User $admin;

    private User $cashier;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = User::factory()->admin()->create(['name' => 'Amaya Fernando']);
        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
        $this->shift = app(ShiftService::class)->open($this->cashier, 5000);
    }

    public function test_the_overview_works_out_profit_and_a_split_aware_payment_breakdown_without_cancelled_sales(): void
    {
        $product = Product::factory()->create(['selling_price' => 2000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 10, 1200, location: 'showroom');

        $this->checkout($product, [['method' => 'cash']])->assertCreated();
        $this->checkout($product, [['method' => 'cash', 'amount' => 500], ['method' => 'card']])->assertCreated();
        $this->checkout($product, [['method' => 'transfer']])->assertCreated();
        Sale::query()->latest('id')->firstOrFail()->update(['status' => 'cancelled']);

        $this->addExpense(['category' => 'rent', 'amount' => 1000])->assertCreated();
        Supplier::factory()->create(['name' => 'Tech Distributors', 'total_payable' => 9000, 'amount_paid' => 2000, 'balance' => 7000, 'payment_status' => 'partial']);

        $this->actingAs($this->admin)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertViewHas('overview', function (array $overview): bool {
                // Revenue 4,000 − COGS 2,400 = 1,600 gross; − 1,000 expenses = 600 net; margin 40%.
                $this->assertSame([4000, 2400, 1600, 1000, 600, 40.0, 2], [
                    (int) $overview['revenue'], (int) $overview['cogs'], (int) $overview['gross_profit'],
                    (int) $overview['expenses'], (int) $overview['net_profit'], $overview['margin'], $overview['sales_count'],
                ]);
                $this->assertSame(
                    ['cash' => [2500.0, 62.5], 'card' => [1500.0, 37.5], 'transfer' => [0.0, 0.0], 'kokopay' => [0.0, 0.0]],
                    collect($overview['payment_methods'])->mapWithKeys(fn (array $row): array => [$row['method'] => [(float) $row['amount'], (float) $row['percent']]])->all(),
                );
                $this->assertSame([['Kasun Perera', 2, 4000.0]], array_map(fn (array $row): array => [$row['cashier_name'], $row['sales_count'], (float) $row['revenue']], $overview['cashiers']));
                $this->assertSame([['Tech Distributors', 7000.0]], array_map(fn (array $row): array => [$row['name'], $row['balance']], $overview['payables']));

                return true;
            })
            ->assertSee('Cashier performance')
            ->assertSee('Supplier Payables');

        $this->actingAs($this->admin)
            ->get(route('finance.export', ['tab' => 'overview']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_the_daily_balance_runs_a_closing_balance_from_the_opening_balance(): void
    {
        $product = Product::factory()->create(['selling_price' => 3000, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 5, 1000, location: 'showroom');

        $this->travelTo(now()->startOfMonth()->addDay()->setTime(10, 0));
        $this->checkout($product, [['method' => 'cash']])->assertCreated();
        $this->addExpense(['category' => 'utilities', 'amount' => 500])->assertCreated();

        $this->travel(1)->days();
        $this->checkout($product, [['method' => 'card']])->assertCreated();

        $response = $this->actingAs($this->admin)->get(route('finance.index', ['tab' => 'daily']))->assertOk();
        $days = collect($response->viewData('days'))->keyBy('date');
        $first = now()->subDay()->toDateString();
        $second = now()->toDateString();

        $this->assertSame([3000.0, 500.0, 2500.0], [(float) $days[$first]['income'], (float) $days[$first]['expenses'], (float) $days[$first]['net']]);
        $this->assertSame([3000.0, 0.0, 3000.0], [(float) $days[$second]['income'], (float) $days[$second]['expenses'], (float) $days[$second]['net']]);
        $this->assertSame(now()->startOfMonth()->toDateString(), $days->keys()->first());

        $csv = $this->actingAs($this->admin)
            ->get(route('finance.export', ['tab' => 'daily', 'opening' => 10000]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString("{$first},10000,3000,500,2500,12500", $csv);
        $this->assertStringContainsString("{$second},12500,3000,0,3000,15500", $csv);
    }

    public function test_an_expense_paid_from_the_drawer_lowers_expected_cash_until_it_is_deleted(): void
    {
        $this->addExpense(['category' => 'maintenance', 'amount' => 750, 'note' => 'Fan repair', 'from_drawer' => true, 'shift_id' => $this->shift->id])
            ->assertCreated()
            ->assertJsonPath('message', "EXP-00001 of Rs. 750 added — paid from SHIFT-00001's drawer.");

        $expense = Expense::query()->sole();
        $this->assertSame(['maintenance', '750.00', 'Fan repair', 'Amaya Fernando', 'SHIFT-00001'], [
            $expense->category, $expense->amount, $expense->note, $expense->paid_by_name, $expense->shift_no,
        ]);
        $this->assertSame(4250.0, ShiftService::expectedCash($this->shift->fresh()));

        $this->actingAs($this->admin)
            ->delete(route('finance.expenses.destroy', $expense))
            ->assertSessionHas('status', 'EXP-00001 has been deleted.');

        $this->assertSame(5000.0, ShiftService::expectedCash($this->shift->fresh()));
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_deleting_a_drawer_expense_after_its_shift_closed_leaves_the_shift_alone(): void
    {
        $this->addExpense(['category' => 'other', 'amount' => 200, 'from_drawer' => true, 'shift_id' => $this->shift->id])->assertCreated();
        app(ShiftService::class)->close($this->shift, $this->cashier, 4800);

        $this->actingAs($this->admin)->delete(route('finance.expenses.destroy', Expense::query()->sole()))->assertRedirect();

        $this->assertSame(['200.00', '4800.00'], [$this->shift->fresh()->cash_expenses_total, $this->shift->fresh()->expected_cash]);
    }

    public function test_salaries_cannot_be_added_or_deleted_as_plain_expenses_and_closed_shifts_cannot_pay(): void
    {
        $this->addExpense(['category' => 'salaries', 'amount' => 1000])
            ->assertJsonValidationErrors(['category' => 'Choose Rent, Utilities, Maintenance, Marketing or Other. Salaries are recorded from the Salary page.']);

        $this->addExpense(['category' => 'rent', 'amount' => 1000, 'from_drawer' => true])
            ->assertJsonValidationErrors(['shift_id' => 'Choose the open shift the cash was taken from.']);

        app(ShiftService::class)->close($this->shift, $this->cashier, 5000);

        $this->addExpense(['category' => 'rent', 'amount' => 1000, 'from_drawer' => true, 'shift_id' => $this->shift->id])
            ->assertJsonValidationErrors(['shift_id' => 'SHIFT-00001 is not open — choose an open shift to pay from.']);

        $this->actingAs($this->admin)->postJson(route('salary.store'), [
            'user_id' => $this->cashier->id, 'type' => 'monthly', 'amount' => 30000, 'period_label' => 'August 2026',
        ])->assertCreated();

        $this->actingAs($this->admin)
            ->delete(route('finance.expenses.destroy', Expense::query()->sole()))
            ->assertSessionHas('error', 'EXP-00001 records SAL-00001 — delete it from the Salary page instead.');

        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_only_an_admin_can_force_close_a_shift_with_the_same_expected_and_variance_maths(): void
    {
        $manager = User::factory()->create(['role' => 'Manager']);
        $this->shift->update(['cash_sales_total' => 3000, 'cash_expenses_total' => 500]);

        $this->actingAs($manager)
            ->postJson(route('finance.shifts.force-close', $this->shift), ['counted_cash' => 7000])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->getJson(route('finance.shifts.show', $this->shift))
            ->assertOk()
            ->assertJsonPath('shift.expected_cash', 7500)
            ->assertJsonPath('shift.urls.review', null);

        $this->actingAs($this->admin)
            ->postJson(route('finance.shifts.force-close', $this->shift), ['counted_cash' => 7600, 'note' => 'Cashier went home sick'])
            ->assertOk()
            ->assertJsonPath('message', 'SHIFT-00001 has been force closed.')
            ->assertJsonPath('shift.variance', 100)
            ->assertJsonPath('shift.urls.forceClose', null);

        $shift = $this->shift->fresh();
        $this->assertSame(['closed', true, $this->admin->id, 'Amaya Fernando', '7500.00', '100.00', 'pending', 'Cashier went home sick'], [
            $shift->status, $shift->force_closed, $shift->closed_by_id, $shift->closed_by_name,
            $shift->expected_cash, $shift->variance, $shift->review_status, $shift->close_note,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('finance.shifts.force-close', $this->shift), ['counted_cash' => 7600])
            ->assertJsonValidationErrors(['shift' => 'SHIFT-00001 is already closed.']);
    }

    public function test_a_manager_reviews_only_closed_shifts(): void
    {
        $manager = User::factory()->create(['role' => 'Manager', 'name' => 'Nadeesha']);
        $staff = User::factory()->create(['role' => 'Staff', 'permissions' => ['finance.view' => true]]);

        $this->actingAs($manager)
            ->postJson(route('finance.shifts.review', $this->shift), ['decision' => 'approved'])
            ->assertJsonValidationErrors(['shift' => 'SHIFT-00001 is still open — close it before reviewing.']);

        app(ShiftService::class)->close($this->shift, $this->cashier, 4900);

        $this->actingAs($staff)
            ->postJson(route('finance.shifts.review', $this->shift), ['decision' => 'approved'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson(route('finance.shifts.review', $this->shift), ['decision' => 'flagged', 'note' => 'Rs. 100 short again'])
            ->assertOk()
            ->assertJsonPath('message', 'SHIFT-00001 flagged.')
            ->assertJsonPath('shift.review_label', 'Flagged');

        $shift = $this->shift->fresh();
        $this->assertSame(['flagged', 'Nadeesha', 'Rs. 100 short again'], [$shift->review_status, $shift->reviewed_by_name, $shift->review_note]);
        $this->assertNotNull($shift->reviewed_at);
    }

    public function test_the_shifts_tab_filters_and_flags_long_open_shifts(): void
    {
        $other = User::factory()->create(['role' => 'Cashier', 'name' => 'Ruwan Jayasuriya']);
        $this->travel(-3)->days();
        $old = app(ShiftService::class)->open($other, 1000);
        $this->travelBack();

        $this->actingAs($this->admin)
            ->get(route('finance.index', ['tab' => 'shifts']))
            ->assertOk()
            ->assertSee($this->shift->shift_no)
            ->assertSee($old->shift_no)
            ->assertSee('open 3 days');

        $this->actingAs($this->admin)
            ->get(route('finance.index', ['tab' => 'shifts', 'search' => 'Ruwan']))
            ->assertOk()
            ->assertViewHas('shifts', fn ($shifts): bool => $shifts->pluck('id')->all() === [$old->id]);

        $this->actingAs($this->admin)
            ->get(route('finance.index', ['tab' => 'shifts', 'cashier_id' => $this->cashier->id]))
            ->assertViewHas('shifts', fn ($shifts): bool => $shifts->pluck('id')->all() === [$this->shift->id]);
    }

    public function test_each_finance_tab_renders_and_exports_and_finance_view_is_required(): void
    {
        $this->addExpense(['category' => 'marketing', 'amount' => 1500, 'note' => 'Facebook ads'])->assertCreated();

        foreach (['overview' => 'Net Profit', 'daily' => 'Opening balance', 'shifts' => 'SHIFT-00001', 'expenses' => 'Facebook ads'] as $tab => $text) {
            $this->actingAs($this->admin)->get(route('finance.index', ['tab' => $tab]))->assertOk()->assertSee($text);
            $this->actingAs($this->admin)->get(route('finance.export', ['tab' => $tab]))->assertOk()->assertDownload();
        }

        $blocked = User::factory()->create(['role' => 'Staff', 'permissions' => ['finance.view' => false]]);

        $this->actingAs($blocked)->get(route('finance.index'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($blocked)->get(route('finance.export'))->assertForbidden();

        // Cashiers can view Finance by default but not add or delete expenses.
        $this->actingAs($this->cashier)->get(route('finance.index', ['tab' => 'expenses']))->assertOk()->assertDontSee('Add Expense');
        $this->actingAs($this->cashier)->postJson(route('finance.expenses.store'), ['category' => 'rent', 'amount' => 1])->assertForbidden();
        $this->actingAs($this->cashier)->delete(route('finance.expenses.destroy', Expense::query()->sole()))->assertForbidden();
        $this->assertDatabaseCount('expenses', 1);
    }

    /**
     * @param  list<array{method: string, amount?: float|int}>  $payments
     */
    private function checkout(Product $product, array $payments): TestResponse
    {
        return $this->actingAs($this->cashier)->postJson(route('sales.store'), [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => $payments,
            'expected_total' => (float) $product->selling_price,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function addExpense(array $data): TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('finance.expenses.store'), $data);
    }
}
