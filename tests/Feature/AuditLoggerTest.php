<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_diff_lists_only_allowed_fields_present_in_the_patch_that_change(): void
    {
        $changes = (new AuditLogger)->diff(
            ['amount' => '1500.00', 'note' => null, 'method' => 'cash', 'reference' => '00123', 'balance' => '10.00'],
            ['amount' => 1500, 'note' => '  ', 'method' => 'cheque', 'balance' => 0, 'reference' => '00124'],
            ['amount', 'note', 'method', 'reference', 'customer_name'],
        );

        $this->assertSame([
            ['field' => 'method', 'before' => 'cash', 'after' => 'cheque'],
            ['field' => 'reference', 'before' => '00123', 'after' => '00124'],
        ], $changes);
    }

    public function test_write_stores_the_changes_and_skips_when_nothing_changed(): void
    {
        $user = User::factory()->create(['name' => 'Kasun']);
        $logger = new AuditLogger;

        $this->assertNull($logger->write('grns', 7, 'GRN-00007', [], $user));
        $this->assertDatabaseCount('audit_logs', 0);

        $log = $logger->write('grns', 7, 'GRN-00007', [['field' => 'note', 'before' => null, 'after' => 'INV-1']], $user);

        $this->assertTrue($log->is(AuditLog::query()->sole()));
        $this->assertSame(['grns', '7', 'GRN-00007', $user->id, 'Kasun'], [
            $log->collection_name, $log->doc_id, $log->label, $log->performed_by_id, $log->performed_by_name,
        ]);
        $this->assertSame([['field' => 'note', 'before' => null, 'after' => 'INV-1']], $log->fresh()->changes);
    }
}
