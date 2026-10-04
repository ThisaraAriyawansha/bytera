<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_recording_a_payment_lowers_the_balance_and_snapshots_it(): void
    {
        $this->freezeSecond();
        $supplier = Supplier::factory()->owing(10000)->create(['name' => 'Acme']);

        $this->actingAs(User::factory()->admin()->create(['name' => 'Kasun']))
            ->postJson(route('suppliers.payments.store', $supplier), [
                'amount' => 2500.5,
                'method' => 'cheque',
                'reference' => ' CHQ-001 ',
                'note' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Payment PAY-00001 of Rs. 2,500.50 recorded.');

        $payment = SupplierPayment::query()->sole();
        $this->assertSame(['PAY-00001', 'Acme', '2500.50', 'cheque', 'CHQ-001', '', '10000.00', '7499.50', 'Kasun'], [
            $payment->payment_no, $payment->supplier_name, $payment->amount, $payment->method, $payment->reference,
            $payment->note, $payment->balance_before, $payment->balance_after, $payment->paid_by_name,
        ]);

        $supplier->refresh();
        $this->assertSame(['10000.00', '2500.50', '7499.50', 'partial'], [$supplier->total_payable, $supplier->amount_paid, $supplier->balance, $supplier->payment_status]);
        $this->assertTrue($supplier->last_payment_at->equalTo(now()));
    }

    public function test_amount_must_be_positive_and_not_more_than_the_balance(): void
    {
        $supplier = Supplier::factory()->owing(10000, 2500)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 0, 'method' => 'cash'])
            ->assertJsonValidationErrors(['amount' => 'The amount must be greater than 0.']);

        $this->actingAs($admin)
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 7500.01, 'method' => 'card'])
            ->assertJsonValidationErrors(['method']);

        $this->actingAs($admin)
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 7500.01, 'method' => 'cash'])
            ->assertJsonValidationErrors(['amount' => 'Payment of Rs. 7,500.01 exceeds the outstanding balance of Rs. 7,500']);

        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertSame('7500.00', $supplier->fresh()->balance);
    }

    public function test_recording_a_payment_needs_its_permission(): void
    {
        $supplier = Supplier::factory()->owing(1000)->create();

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 100, 'method' => 'cash'])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'Manager', 'permissions' => ['suppliers.recordPayment' => true]]))
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 100, 'method' => 'cash'])
            ->assertCreated();
    }

    public function test_editing_the_amount_recalculates_the_balance_and_is_audit_logged(): void
    {
        $supplier = Supplier::factory()->owing(10000)->create();
        $admin = User::factory()->admin()->create(['name' => 'Admin User']);
        $this->actingAs($admin)->postJson(route('suppliers.payments.store', $supplier), ['amount' => 4000, 'method' => 'cash'])->assertCreated();
        $payment = SupplierPayment::query()->sole();

        $this->actingAs($admin)
            ->putJson(route('suppliers.payments.update', [$supplier, $payment]), [
                'amount' => 10000, 'method' => 'bank_transfer', 'reference' => 'TRX-9', 'note' => '',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Payment PAY-00001 updated.');

        $supplier->refresh();
        $payment->refresh();
        $this->assertSame(['10000.00', '0.00', 'paid'], [$supplier->amount_paid, $supplier->balance, $supplier->payment_status]);
        $this->assertSame(['10000.00', 'bank_transfer', 'TRX-9', '10000.00', '0.00'], [
            $payment->amount, $payment->method, $payment->reference, $payment->balance_before, $payment->balance_after,
        ]);

        $log = AuditLog::query()->sole();
        $this->assertSame(['supplier_payments', (string) $payment->id, 'PAY-00001', 'Admin User'], [
            $log->collection_name, $log->doc_id, $log->label, $log->performed_by_name,
        ]);
        $this->assertSame([
            ['field' => 'amount', 'before' => '4000.00', 'after' => 10000],
            ['field' => 'method', 'before' => 'cash', 'after' => 'bank_transfer'],
            ['field' => 'reference', 'before' => null, 'after' => 'TRX-9'],
        ], $log->changes);
    }

    public function test_editing_rejects_overpaying_and_writes_nothing_when_unchanged(): void
    {
        $supplier = Supplier::factory()->owing(10000)->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('suppliers.payments.store', $supplier), ['amount' => 4000, 'method' => 'cash'])->assertCreated();
        $payment = SupplierPayment::query()->sole();

        $this->actingAs($admin)
            ->putJson(route('suppliers.payments.update', [$supplier, $payment]), ['amount' => 10000.5, 'method' => 'cash'])
            ->assertJsonValidationErrors(['amount' => 'Payment of Rs. 10,000.50 exceeds the outstanding balance of Rs. 10,000']);

        $this->actingAs($admin)
            ->putJson(route('suppliers.payments.update', [$supplier, $payment]), ['amount' => '4000.00', 'method' => 'cash', 'reference' => '', 'note' => ''])
            ->assertOk()
            ->assertJsonPath('message', 'Nothing changed.');

        $this->assertSame(['4000.00', '6000.00'], [$supplier->fresh()->amount_paid, $supplier->fresh()->balance]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_editing_needs_its_permission_and_the_payment_must_belong_to_the_supplier(): void
    {
        $supplier = Supplier::factory()->owing(10000)->create();
        $other = Supplier::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson(route('suppliers.payments.store', $supplier), ['amount' => 100, 'method' => 'cash'])->assertCreated();
        $payment = SupplierPayment::query()->sole();

        $this->actingAs(User::factory()->create(['role' => 'Manager', 'permissions' => ['suppliers.recordPayment' => true]]))
            ->putJson(route('suppliers.payments.update', [$supplier, $payment]), ['amount' => 50, 'method' => 'cash'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson(route('suppliers.payments.update', [$other, $payment]), ['amount' => 50, 'method' => 'cash'])
            ->assertNotFound();

        $this->assertSame('100.00', $payment->fresh()->amount);
    }

    public function test_report_lists_payments_in_range_with_outstanding_balances_and_exports_csv(): void
    {
        $acme = Supplier::factory()->owing(10000)->create(['name' => 'Acme Traders']);
        $beta = Supplier::factory()->owing(3000)->create(['name' => 'Beta Imports']);
        $admin = User::factory()->admin()->create(['name' => 'Kasun']);
        $this->actingAs($admin)->postJson(route('suppliers.payments.store', $acme), ['amount' => 1000, 'method' => 'cash'])->assertCreated();
        $this->actingAs($admin)->postJson(route('suppliers.payments.store', $beta), ['amount' => 3000, 'method' => 'cheque'])->assertCreated();
        $this->actingAs($admin)->postJson(route('suppliers.payments.store', $acme), ['amount' => 500, 'method' => 'cash'])->assertCreated();
        SupplierPayment::query()->where('payment_no', 'PAY-00003')->update(['created_at' => now()->subDays(40)]);

        $this->actingAs($admin)->get(route('suppliers.payments.index'))
            ->assertOk()
            ->assertSee(['PAY-00001', 'PAY-00002', 'Total paid', 'Rs. 4,000'])
            ->assertDontSee('PAY-00003')
            ->assertSeeInOrder(['Outstanding Balances', 'Acme Traders', 'Rs. 8,500', 'Partial'])
            ->assertViewHas('balances', fn ($balances): bool => $balances->pluck('name')->all() === ['Acme Traders']);

        $this->actingAs($admin)->get(route('suppliers.payments.index', ['search' => 'beta']))
            ->assertSee('PAY-00002')
            ->assertDontSee('PAY-00001');

        $csv = $this->actingAs($admin)
            ->get(route('suppliers.payments.export', ['from' => now()->subDays(60)->toDateString()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertSame(['Payment No.', 'Supplier', 'Amount', 'Method', 'Reference', 'Note', 'Balance Before', 'Balance After', 'Paid By', 'Date'], $rows[0]);
        $this->assertSame(['PAY-00002', 'PAY-00001', 'PAY-00003'], array_column(array_slice($rows, 1), 0));
        $this->assertSame(['Beta Imports', '3000.00', 'Cheque'], array_slice($rows[1], 1, 3));
    }
}
