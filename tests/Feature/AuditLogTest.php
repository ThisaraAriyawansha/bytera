<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Amaya Fernando']);
    }

    public function test_the_audit_log_lists_recent_edits_with_their_record_and_changed_fields(): void
    {
        $logger = app(AuditLogger::class);
        $logger->write('sales', 12, 'INV-00012', [
            ['field' => 'customer_name', 'before' => 'Walk-in Customer', 'after' => 'Saman Kumara'],
            ['field' => 'note', 'before' => null, 'after' => 'Delivered to office'],
        ], $this->admin);
        $logger->write('grns', 3, 'GRN-00003', [['field' => 'Dell Mouse · cost_price', 'before' => '1200.00', 'after' => 1250]], $this->admin);

        $this->travel(-45)->days();
        $logger->write('jobs', 9, 'JOB-00009', [['field' => 'model', 'before' => 'X1', 'after' => 'X2']], $this->admin);
        $this->travelBack();

        $this->actingAs($this->admin)
            ->get(route('audit-log.index'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->pluck('label')->all() === ['GRN-00003', 'INV-00012'])
            ->assertSeeInOrder(['Bill ·', 'INV-00012', 'Customer name', 'Note'])
            ->assertSee('Dell Mouse · Cost price')
            ->assertSee('Saman Kumara')
            ->assertDontSee('JOB-00009');
    }

    public function test_the_audit_log_filters_by_record_type_search_and_date_range(): void
    {
        $logger = app(AuditLogger::class);
        $cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Kasun Perera']);
        $change = [['field' => 'note', 'before' => 'a', 'after' => 'b']];

        $logger->write('sales', 1, 'INV-00001', $change, $this->admin);
        $logger->write('stock_outs', 2, 'SO-00002', $change, $cashier);
        $logger->write('supplier_payments', 3, 'PAY-00003', $change, $this->admin);

        $labels = fn (array $query): array => $this->actingAs($this->admin)
            ->get(route('audit-log.index', $query))
            ->assertOk()
            ->viewData('logs')
            ->pluck('label')
            ->all();

        $this->assertSame(['SO-00002'], $labels(['type' => 'stock_outs']));
        $this->assertSame(['PAY-00003', 'SO-00002', 'INV-00001'], $labels(['type' => 'not-a-type']));
        $this->assertSame(['PAY-00003'], $labels(['search' => 'pay-0000']));
        $this->assertSame(['SO-00002'], $labels(['search' => 'Kasun']));
        $this->assertSame([], $labels(['from' => now()->subDays(10)->toDateString(), 'to' => now()->subDays(2)->toDateString()]));
    }

    public function test_the_audit_log_needs_the_audit_log_view_permission(): void
    {
        $manager = User::factory()->create(['role' => 'Manager', 'permissions' => ['auditLog.view' => false]]);

        $this->actingAs($manager)->get(route('audit-log.index'))->assertForbidden()->assertSee('Access Restricted');
    }

    public function test_field_labels_and_display_values_read_naturally(): void
    {
        $this->assertSame('Customer name', AuditLog::fieldLabel('customer_name'));
        $this->assertSame('HP Toner · Selling price', AuditLog::fieldLabel('HP Toner · selling_price'));
        $this->assertNull(AuditLog::displayValue(''));
        $this->assertSame('Yes', AuditLog::displayValue(true));
        $this->assertSame('RAM · 8GB; SSD', AuditLog::displayValue([['id' => 'a1', 'name' => 'RAM', 'spec' => '8GB'], ['id' => 'b2', 'name' => 'SSD', 'spec' => '']]));
    }
}
