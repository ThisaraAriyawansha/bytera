<?php

namespace Tests\Feature;

use App\Mail\SupplierStatementMail;
use App\Models\Grn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_payment_status_follows_the_balance_rule(): void
    {
        $this->assertSame('paid', SupplierService::paymentStatus(0, 0));
        $this->assertSame('paid', SupplierService::paymentStatus(-50, 1000));
        $this->assertSame('outstanding', SupplierService::paymentStatus(1000, 1000));
        $this->assertSame('partial', SupplierService::paymentStatus(999.99, 1000));
    }

    public function test_page_shows_the_outstanding_summary_and_searches_name_or_phone(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $acme = Supplier::factory()->owing(50000, 20000)->create(['name' => 'Acme Traders', 'phone' => '0771111111', 'last_payment_at' => now()->subDays(45)]);
        Supplier::factory()->owing(8000)->create(['name' => 'Beta Imports', 'phone' => '0712222222', 'created_at' => now()->subDays(3)]);
        Supplier::factory()->create(['name' => 'Gamma Paid', 'phone' => '0703333333']);
        $user = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($user)->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Rs. 38,000 outstanding')
            ->assertSeeInOrder(['Outstanding Balances', 'Acme Traders', 'Rs. 30,000', now()->subDays(45)->format('M j, Y'), '45 days', 'Beta Imports', 'Never', '3 days'])
            ->assertSee(['Partial', 'Outstanding', 'Paid']);

        $this->actingAs($user)->get(route('suppliers.index', ['search' => '0712']))
            ->assertSee('1 supplier found')
            ->assertSee('Beta Imports')
            ->assertDontSee('Gamma Paid');

        $this->actingAs($user)->getJson(route('suppliers.show', $acme))
            ->assertOk()
            ->assertJsonPath('supplier.balance', 30000)
            ->assertJsonPath('supplier.payment_status', 'partial')
            ->assertJsonCount(0, 'payments');
    }

    public function test_unpaid_for_counts_from_the_first_grn_when_never_paid(): void
    {
        $supplier = Supplier::factory()->owing(1000)->create(['created_at' => now()->subDays(90)]);
        $user = User::factory()->create();
        Grn::query()->create([
            'grn_no' => 'GRN-00001', 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'total_cost' => 1000,
            'received_by_id' => $user->id, 'received_by_name' => $user->name, 'note' => '', 'location' => 'stores',
        ])->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->assertSame(10, $supplier->unpaidForDays());
    }

    public function test_a_new_supplier_owes_nothing_whatever_the_form_sends(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->postJson(route('suppliers.store'), [
                'name' => ' Acme Traders ',
                'phone' => '0771234567',
                'email' => 'Sales@Acme.LK',
                'address' => '',
                'balance' => 99999,
            ])
            ->assertCreated();

        $supplier = Supplier::query()->sole();
        $this->assertSame(['Acme Traders', 'sales@acme.lk', null], [$supplier->name, $supplier->email, $supplier->address]);
        $this->assertSame(['0.00', '0.00', '0.00', 'paid'], [$supplier->total_payable, $supplier->amount_paid, $supplier->balance, $supplier->payment_status]);
    }

    public function test_name_phone_and_a_valid_email_are_required(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->postJson(route('suppliers.store'), ['name' => '', 'phone' => '', 'email' => 'a@b.com; c@d.com'])
            ->assertJsonValidationErrors(['name', 'phone', 'email' => 'Enter a valid email address.']);
    }

    public function test_editing_contact_needs_its_permission_and_never_touches_balances(): void
    {
        $supplier = Supplier::factory()->owing(5000, 1000)->create(['name' => 'Acme']);
        $payload = ['name' => 'Acme Traders', 'phone' => '0779999999', 'balance' => 0, 'amount_paid' => 5000];

        $this->actingAs(User::factory()->create(['role' => 'Staff', 'permissions' => ['suppliers.editContact' => false]]))
            ->putJson(route('suppliers.update', $supplier), $payload)
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->putJson(route('suppliers.update', $supplier), $payload)
            ->assertOk();

        $supplier->refresh();
        $this->assertSame(['Acme Traders', '0779999999'], [$supplier->name, $supplier->phone]);
        $this->assertSame(['5000.00', '1000.00', '4000.00', 'partial'], [$supplier->total_payable, $supplier->amount_paid, $supplier->balance, $supplier->payment_status]);
    }

    public function test_suppliers_page_needs_the_view_permission(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'permissions' => ['suppliers.view' => false]]);

        $this->actingAs($user)->get(route('suppliers.index'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($user)->get(route('suppliers.payments.index'))->assertForbidden();
    }

    public function test_admin_can_email_the_account_statement(): void
    {
        Mail::fake();
        $supplier = Supplier::factory()->owing(5000, 1500)->create(['email' => 'sales@acme.lk']);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('suppliers.statement', $supplier))
            ->assertOk()
            ->assertJsonPath('message', 'Statement sent to sales@acme.lk.');

        Mail::assertSent(SupplierStatementMail::class, fn (SupplierStatementMail $mail): bool => $mail->hasTo('sales@acme.lk')
            && $mail->hasSubject('Account Statement - M-Fixpro')
            && str_contains($mail->render(), 'Rs. 3,500'));
        $this->assertNotNull($supplier->fresh()->last_statement_sent_at);
    }

    public function test_statement_needs_the_permission_an_admin_role_and_an_email(): void
    {
        Mail::fake();
        $supplier = Supplier::factory()->create(['email' => 'sales@acme.lk']);
        $noEmail = Supplier::factory()->create(['email' => null]);

        $this->actingAs(User::factory()->create(['role' => 'Manager', 'permissions' => ['suppliers.sendStatement' => true]]))
            ->postJson(route('suppliers.statement', $supplier))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create(['permissions' => ['suppliers.sendStatement' => false]]))
            ->postJson(route('suppliers.statement', $supplier))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('suppliers.statement', $noEmail))
            ->assertJsonValidationErrors(['email' => 'This supplier has no email address. Add one with Edit first.']);

        Mail::assertNothingSent();
        $this->assertNull($supplier->fresh()->last_statement_sent_at);
    }
}
