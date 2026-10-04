<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataToolTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_only_super_admin_can_use_data_tools(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('settings.data.export', ['table' => 'users', 'format' => 'csv']))->assertForbidden();
        $this->actingAs($admin)->delete(route('settings.data.clean', 'audit_logs'), ['confirmation' => 'audit_logs'])->assertForbidden();
    }

    public function test_csv_export_streams_rows_without_credentials(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['email' => 'boss@example.com']);

        $response = $this->actingAs($superAdmin)
            ->get(route('settings.data.export', ['table' => 'users', 'format' => 'csv']))
            ->assertOk()
            ->assertDownload();

        $csv = $response->streamedContent();
        $header = str_getcsv(strtok($csv, "\n"), escape: '');

        $this->assertContains('email', $header);
        $this->assertNotContains('password', $header);
        $this->assertNotContains('remember_token', $header);
        $this->assertStringContainsString('boss@example.com', $csv);
    }

    public function test_json_export_is_valid_json(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('settings.data.export', ['table' => 'counters', 'format' => 'json']))
            ->assertOk();

        $rows = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('invoice', collect($rows)->firstWhere('name', 'invoice')['name']);
    }

    public function test_unknown_tables_and_formats_are_rejected(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->get(route('settings.data.export', ['table' => 'sessions', 'format' => 'csv']))->assertNotFound();
        $this->actingAs($superAdmin)->get('/settings/data/users/export/xml')->assertNotFound();
        $this->actingAs($superAdmin)->delete(route('settings.data.clean', 'users'), ['confirmation' => 'users'])->assertNotFound();
    }

    public function test_clean_requires_the_typed_table_name(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->createAuditLog($superAdmin);

        $this->actingAs($superAdmin)
            ->delete(route('settings.data.clean', 'audit_logs'), ['confirmation' => 'audit'])
            ->assertSessionHasErrorsIn('clean', ['confirmation' => 'Type audit_logs exactly to confirm.']);
        $this->assertDatabaseCount('audit_logs', 1);

        $this->actingAs($superAdmin)
            ->delete(route('settings.data.clean', 'audit_logs'), ['confirmation' => 'audit_logs'])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'Cleaned audit_logs: 1 rows deleted.');
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function createAuditLog(User $user): void
    {
        AuditLog::query()->create([
            'collection_name' => 'sales',
            'doc_id' => '1',
            'label' => 'INV-00001',
            'changes' => [],
            'performed_by_id' => $user->id,
            'performed_by_name' => $user->name,
        ]);
    }
}
