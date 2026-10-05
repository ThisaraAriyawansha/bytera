<?php

namespace Tests\Feature;

use App\Mail\SalaryPayslipMail;
use App\Models\Expense;
use App\Models\Job;
use App\Models\Product;
use App\Models\SalaryPayment;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SalaryTest extends TestCase
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
        $this->cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera', 'email' => 'kasun@example.com']);
        $this->shift = app(ShiftService::class)->open($this->cashier, 5000);
    }

    public function test_a_cash_salary_paid_from_an_open_shift_lowers_its_expected_cash_and_deleting_it_restores_it(): void
    {
        $sale = $this->sell(2000);
        $job = Job::factory()->create(['repair_cost' => 8000, 'estimated_cost' => 5000]);
        $this->assertSame(7000.0, ShiftService::expectedCash($this->shift->fresh()));

        // Hybrid: 1,500 monthly + (2,000 + 8,000) × 5% = 2,000.
        $this->actingAs($this->admin)->postJson(route('salary.store'), [
            'user_id' => $this->cashier->id,
            'type' => 'hybrid',
            'commission_percent' => 5,
            'amount' => 2000,
            'period_label' => 'August 2026',
            'note' => 'Thanks for the busy month',
            'from_drawer' => true,
            'shift_id' => $this->shift->id,
            'sale_ids' => [$sale->id],
            'job_ids' => [$job->id],
        ])->assertCreated()->assertJsonPath('message', "SAL-00001 of Rs. 2,000 issued to Kasun Perera — paid from SHIFT-00001's drawer.");

        $payment = SalaryPayment::query()->sole();
        $expense = Expense::query()->sole();

        $this->assertSame(5000.0, ShiftService::expectedCash($this->shift->fresh()));
        $this->assertSame(['hybrid', '2000.00', '10000.00', '5.00', 'Cashier', 'SHIFT-00001', $expense->id], [
            $payment->type, $payment->amount, $payment->commission_base, $payment->commission_percent,
            $payment->user_role, $payment->shift_no, $payment->linked_expense_id,
        ]);
        $this->assertSame([
            ['kind' => 'sale', 'id' => $sale->id, 'number' => $sale->invoice_no, 'amount' => 2000],
            ['kind' => 'job', 'id' => $job->id, 'number' => $job->job_no, 'amount' => 8000],
        ], $payment->commission_items);
        $this->assertSame(['EXP-00001', 'salaries', '2000.00', 'Salary payment SAL-00001 — Kasun Perera (August 2026)', $payment->id, $this->shift->id], [
            $expense->expense_no, $expense->category, $expense->amount, $expense->note, $expense->linked_salary_payment_id, $expense->shift_id,
        ]);
        $this->assertSame($payment->id, $sale->fresh()->commission_payment_id);
        $this->assertSame($payment->id, $job->fresh()->commission_payment_id);

        $this->actingAs($this->admin)
            ->getJson(route('salary.show', $payment))
            ->assertOk()
            ->assertJsonPath('payment.calculation', [
                ['label' => 'Commission: Rs. 10,000 × 5%', 'amount' => 500],
                ['label' => 'Monthly salary', 'amount' => 1500],
                ['label' => 'Amount paid', 'amount' => 2000],
            ])
            ->assertJsonPath('payment.expense_no', 'EXP-00001');

        $this->actingAs($this->admin)
            ->delete(route('salary.destroy', $payment))
            ->assertRedirect(route('salary.index', ['tab' => 'history']))
            ->assertSessionHas('status', "SAL-00001 has been deleted and Rs. 2,000 put back into SHIFT-00001's drawer.");

        $this->assertSame(7000.0, ShiftService::expectedCash($this->shift->fresh()));
        $this->assertSame('0.00', $this->shift->fresh()->cash_expenses_total);
        $this->assertDatabaseCount('salary_payments', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertNull($sale->fresh()->commission_payment_id);
        $this->assertNull($job->fresh()->commission_payment_id);
    }

    public function test_deleting_a_payment_after_its_shift_closed_leaves_the_closed_figures_alone(): void
    {
        $this->issueMonthly(['from_drawer' => true, 'shift_id' => $this->shift->id, 'amount' => 1000])->assertCreated();
        app(ShiftService::class)->close($this->shift, $this->cashier, 4000);

        $this->actingAs($this->admin)
            ->delete(route('salary.destroy', SalaryPayment::query()->sole()))
            ->assertSessionHas('status', 'SAL-00001 has been deleted.');

        $shift = $this->shift->fresh();
        $this->assertSame(['1000.00', '4000.00', '0.00'], [$shift->cash_expenses_total, $shift->expected_cash, $shift->variance]);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_a_monthly_salary_not_from_the_drawer_leaves_shifts_alone_and_ignores_commission_input(): void
    {
        $sale = $this->sell(2000);

        $this->issueMonthly(['amount' => 45000, 'sale_ids' => [$sale->id], 'commission_percent' => 10])->assertCreated();

        $payment = SalaryPayment::query()->sole();
        $this->assertSame(['monthly', '45000.00', null, null, [], null], [
            $payment->type, $payment->amount, $payment->commission_base, $payment->commission_percent, $payment->commission_items, $payment->shift_id,
        ]);
        $this->assertNull($sale->fresh()->commission_payment_id);
        $this->assertNull(Expense::query()->sole()->shift_id);
        $this->assertSame('0.00', $this->shift->fresh()->cash_expenses_total);
    }

    public function test_commission_items_cannot_be_paid_twice_or_on_a_reversed_sale(): void
    {
        $sale = $this->sell(3000);
        $reversed = $this->sell(1000);
        $reversed->update(['status' => 'cancelled']);

        $this->issueCommission([$sale->id])->assertCreated();

        $this->issueCommission([$sale->id])
            ->assertJsonValidationErrors(['items' => "Commission on {$sale->invoice_no} has already been paid in SAL-00001."]);

        $this->issueCommission([$reversed->id])
            ->assertJsonValidationErrors(['items' => "{$reversed->invoice_no} has been reversed and can't earn commission."]);

        // A failed issue rolls everything back: no number, expense or payment is left behind.
        $this->assertDatabaseCount('salary_payments', 1);
        $this->assertDatabaseCount('expenses', 1);

        $this->actingAs($this->admin)
            ->getJson(route('salary.commission-items', ['q' => 'INV']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_commission_picker_lists_unclaimed_sales_and_jobs(): void
    {
        $sale = $this->sell(2500);
        Job::factory()->create(['job_no' => 'JOB-00123', 'repair_cost' => null, 'estimated_cost' => 4000]);

        $this->actingAs($this->admin)
            ->getJson(route('salary.commission-items', ['q' => '123']))
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'job')
            ->assertJsonPath('data.0.number', 'JOB-00123')
            ->assertJsonPath('data.0.amount', 4000);

        $this->actingAs($this->admin)
            ->getJson(route('salary.commission-items', ['q' => $sale->invoice_no]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $sale->id)
            ->assertJsonPath('data.0.amount', 2500);
    }

    public function test_paying_from_a_closed_shift_is_refused(): void
    {
        app(ShiftService::class)->close($this->shift, $this->cashier, 5000);

        $this->issueMonthly(['from_drawer' => true, 'shift_id' => $this->shift->id])
            ->assertJsonValidationErrors(['shift_id' => 'SHIFT-00001 is not open — choose an open shift to pay from.']);

        $this->assertDatabaseCount('salary_payments', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_the_super_admin_is_never_a_payee(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->issueMonthly(['user_id' => $superAdmin->id])
            ->assertJsonValidationErrors(['user_id' => 'The Super Admin is not paid a salary here.']);

        $this->actingAs($this->admin)
            ->get(route('salary.index', ['tab' => 'setup']))
            ->assertOk()
            ->assertSee('Kasun Perera')
            ->assertDontSee($superAdmin->email);
    }

    public function test_employee_setup_saves_and_clears_the_pre_fill_defaults(): void
    {
        $this->actingAs($this->admin)
            ->putJson(route('salary.setup.update', $this->cashier), ['salary_type' => 'hybrid', 'salary_commission_percent' => 2.5])
            ->assertJsonValidationErrors(['salary_monthly_amount' => 'Enter the monthly amount.']);

        $this->actingAs($this->admin)
            ->putJson(route('salary.setup.update', $this->cashier), [
                'salary_type' => 'hybrid',
                'salary_monthly_amount' => 30000,
                'salary_commission_percent' => 2.5,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Salary setup for Kasun Perera: Hybrid Rs. 30,000 + 2.5%.');

        $this->actingAs($this->admin)
            ->putJson(route('salary.setup.update', $this->cashier), ['salary_type' => '', 'salary_monthly_amount' => 30000])
            ->assertOk();

        $cashier = $this->cashier->fresh();
        $this->assertSame([null, null, null, 'Not configured'], [
            $cashier->salary_type, $cashier->salary_monthly_amount, $cashier->salary_commission_percent, $cashier->salarySetupLabel(),
        ]);
    }

    public function test_the_payslip_is_emailed_to_the_employee_on_record(): void
    {
        $this->issueCommission([$this->sell(4000)->id])->assertCreated();
        $payment = SalaryPayment::query()->sole();

        $this->actingAs($this->admin)
            ->postJson(route('salary.email', $payment), ['email' => 'someone-else@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Payslip sent to kasun@example.com.');

        Mail::assertSent(SalaryPayslipMail::class, fn (SalaryPayslipMail $mail): bool => $mail->hasTo('kasun@example.com')
            && $mail->hasSubject('Salary Payment SAL-00001 - M-Fixpro'));
        $this->assertNotNull($payment->fresh()->email_sent_at);

        $mail = new SalaryPayslipMail($payment->fresh(), ShopSetting::current());
        $mail->assertSeeInHtml('How this amount was calculated');
        $mail->assertSeeInText('Commission: Rs. 4,000 × 10%');
    }

    public function test_salary_permissions_are_enforced_on_the_server(): void
    {
        $manager = User::factory()->create(['role' => 'Manager']);

        $this->actingAs($manager)->get(route('salary.index'))->assertForbidden();
        $this->actingAs($manager)->postJson(route('salary.store'), [])->assertForbidden();

        $viewer = User::factory()->create(['role' => 'Manager', 'permissions' => ['salary.view' => true]]);

        $this->actingAs($viewer)->get(route('salary.index'))->assertOk()->assertSee('Payment History');
        $this->actingAs($viewer)
            ->postJson(route('salary.store'), ['user_id' => $this->cashier->id, 'type' => 'monthly', 'amount' => 1, 'period_label' => 'x'])
            ->assertForbidden();
        $this->actingAs($viewer)->putJson(route('salary.setup.update', $this->cashier), ['salary_type' => null])->assertForbidden();

        $this->issueMonthly()->assertCreated();
        $this->actingAs($viewer)->delete(route('salary.destroy', SalaryPayment::query()->sole()))->assertForbidden();
        $this->assertDatabaseCount('salary_payments', 1);
    }

    public function test_each_salary_tab_renders(): void
    {
        $this->issueCommission([$this->sell(1000)->id])->assertCreated();

        foreach (['issue' => 'Amount to Pay', 'history' => 'SAL-00001', 'setup' => 'Salary Setup'] as $tab => $text) {
            $this->actingAs($this->admin)->get(route('salary.index', ['tab' => $tab]))->assertOk()->assertSee($text);
        }
    }

    /**
     * Ring up a cash sale of one product at the given price in the cashier's shift.
     */
    private function sell(float $price): Sale
    {
        $product = Product::factory()->create(['selling_price' => $price, 'low_stock_alert' => 0]);
        $this->receiveStock($product, 1, $price / 2, location: 'showroom');

        $this->actingAs($this->cashier)->postJson(route('sales.store'), [
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash']],
            'expected_total' => $price,
        ])->assertCreated();

        return Sale::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function issueMonthly(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('salary.store'), [
            'user_id' => $this->cashier->id,
            'type' => 'monthly',
            'amount' => 1000,
            'period_label' => 'August 2026',
            ...$overrides,
        ]);
    }

    /**
     * @param  list<int>  $saleIds
     */
    private function issueCommission(array $saleIds): TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('salary.store'), [
            'user_id' => $this->cashier->id,
            'type' => 'commission',
            'commission_percent' => 10,
            'amount' => 400,
            'period_label' => 'August 2026',
            'sale_ids' => $saleIds,
        ]);
    }
}
