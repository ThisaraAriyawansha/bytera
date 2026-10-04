<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Job;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\User;
use App\Services\StockOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOutTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_issuing_for_a_job_consumes_showroom_batches_fifo_and_records_the_cost(): void
    {
        $product = Product::factory()->create(['name' => 'SSD 512GB']);
        $older = $this->receiveStock($product, 2, 100, null, now()->subDays(3), location: 'showroom');
        $newer = $this->receiveStock($product, 5, 130, null, now()->subDay(), location: 'showroom');
        $this->receiveStock($product, 4, 90);
        $job = Job::factory()->create(['job_no' => 'JOB-00042']);
        $manager = User::factory()->create(['role' => 'Manager', 'name' => 'Nimal']);

        $this->actingAs($manager)
            ->postJson(route('stock-out.store'), [
                'location' => 'showroom',
                'recipient' => ' Technician Saman ',
                'reason' => 'job',
                'job_id' => $job->id,
                'reason_detail' => 'Replaced SSD',
                'items' => [['product_id' => $product->id, 'qty' => 3]],
            ])
            ->assertCreated()
            ->assertJsonPath('redirect', route('stock-out.index'));

        $stockOut = StockOut::query()->sole();
        $this->assertSame(['SO-00001', 'showroom', 'Technician Saman', 'job', 'Replaced SSD', $job->id, 'JOB-00042', 'Nimal'], [
            $stockOut->stock_out_no, $stockOut->location, $stockOut->recipient, $stockOut->reason,
            $stockOut->reason_detail, $stockOut->job_id, $stockOut->job_no, $stockOut->issued_by_name,
        ]);

        $product->refresh();
        $this->assertSame([4, 4, 8], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertSame([0, 'depleted'], [$older->fresh()->remaining_qty, $older->fresh()->status]);
        $this->assertSame(4, $newer->fresh()->remaining_qty);

        $item = $stockOut->items()->sole();
        $this->assertSame([3, '110.00', []], [$item->qty, $item->cost_price, $item->serial_numbers]);

        $movement = StockMovement::query()->where('reference_type', 'stock_out')->sole();
        $this->assertSame(['out', -3, 'showroom', 'Technician Saman', 'job', 'Replaced SSD', $job->id, 'JOB-00042', 'Stock out SO-00001'], [
            $movement->type, $movement->qty, $movement->location, $movement->recipient, $movement->reason,
            $movement->reason_detail, $movement->job_id, $movement->job_no, $movement->note,
        ]);
    }

    public function test_issuing_serial_units_marks_them_issued(): void
    {
        $product = Product::factory()->serialTracked()->create();
        $batch = $this->receiveStock($product, 3, 1000, null, null, ['A-1', 'A-2', 'A-3']);
        $other = $this->receiveStock($product, 1, 2000, null, null, ['B-1']);
        $picked = $product->units()->whereIn('serial_number', ['A-2', 'B-1'])->pluck('id')->all();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('stock-out.store'), [
                'location' => 'stores',
                'recipient' => 'Accounts dept',
                'reason' => 'other',
                'reason_detail' => 'Office use',
                'job_id' => 999,
                'items' => [['product_id' => $product->id, 'unit_ids' => $picked]],
            ])
            ->assertCreated();

        $stockOut = StockOut::query()->sole();
        $this->assertNull($stockOut->job_id);

        $issued = $product->units()->whereIn('id', $picked)->get();
        $this->assertSame(['issued'], $issued->pluck('status')->unique()->values()->all());
        $this->assertSame([$stockOut->id], $issued->pluck('stock_out_id')->unique()->values()->all());
        $this->assertNotNull($issued->first()->issued_at);
        $this->assertSame(['A-1', 'A-3'], $product->units()->where('status', 'in_stock')->orderBy('id')->pluck('serial_number')->all());

        $this->assertSame([2, 'active'], [$batch->fresh()->remaining_qty, $batch->fresh()->status]);
        $this->assertSame([0, 'depleted'], [$other->fresh()->remaining_qty, $other->fresh()->status]);

        $product->refresh();
        $this->assertSame([2, 0, 2], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);

        $item = $stockOut->items()->sole();
        $this->assertSame([2, '1500.00', ['A-2', 'B-1']], [$item->qty, $item->cost_price, $item->serial_numbers]);
    }

    public function test_the_header_and_items_are_validated_against_the_chosen_location(): void
    {
        $product = Product::factory()->create(['name' => 'Toner']);
        $this->receiveStock($product, 5, 100);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('stock-out.store'), [
                'location' => 'showroom',
                'recipient' => '',
                'reason' => 'job',
                'items' => [['product_id' => $product->id, 'qty' => 1]],
            ])
            ->assertJsonValidationErrors(['recipient' => 'Enter who the stock is issued to.', 'job_id' => 'Select a job.']);

        $this->actingAs($admin)
            ->postJson(route('stock-out.store'), [
                'location' => 'showroom',
                'recipient' => 'Walk-in',
                'reason' => 'other',
                'items' => [['product_id' => $product->id, 'qty' => 1]],
            ])
            ->assertJsonValidationErrors(['reason_detail' => 'Describe the reason.']);

        $this->actingAs($admin)
            ->postJson(route('stock-out.store'), [
                'location' => 'showroom',
                'recipient' => 'Walk-in',
                'reason' => 'sale',
                'items' => [['product_id' => $product->id, 'qty' => 1]],
            ])
            ->assertJsonValidationErrors(['items.0.qty' => 'Only 0 of "Toner" in Showroom.']);

        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertSame(5, $product->fresh()->stores_stock);
    }

    public function test_creating_a_stock_out_needs_the_create_permission(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);
        $product = Product::factory()->create();
        $this->receiveStock($product, 1, 100);

        $this->actingAs($staff)->get(route('stock-out.index'))->assertOk()->assertDontSee('New Stock Out');
        $this->actingAs($staff)->get(route('stock-out.create'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($staff)
            ->postJson(route('stock-out.store'), [
                'location' => 'stores', 'recipient' => 'X', 'reason' => 'sale', 'items' => [['product_id' => $product->id, 'qty' => 1]],
            ])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->get(route('stock-out.create'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_list_searches_number_recipient_or_staff(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Kasun']);
        $product = Product::factory()->create();
        $this->receiveStock($product, 5, 100);
        $service = app(StockOutService::class);
        $first = $service->issue(['location' => 'stores', 'recipient' => 'Saman', 'reason' => 'sale', 'items' => [['product_id' => $product->id, 'qty' => 1]]], $admin);
        $service->issue(['location' => 'stores', 'recipient' => 'Ruwan', 'reason' => 'sale', 'items' => [['product_id' => $product->id, 'qty' => 1]]], $admin);

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get(route('stock-out.index', ['search' => 'saman']))
            ->assertOk()
            ->assertSee('SO-00001')
            ->assertDontSee('SO-00002');

        $this->actingAs($admin)->getJson(route('stock-out.show', $first))
            ->assertOk()
            ->assertJsonPath('record.number', 'SO-00001')
            ->assertJsonPath('record.reason_label', 'Sale')
            ->assertJsonPath('record.total_cost', 100);
    }

    public function test_admin_edit_changes_only_the_details_keeps_movements_in_step_and_is_audit_logged(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin User']);
        $product = Product::factory()->create();
        $this->receiveStock($product, 3, 100);
        $job = Job::factory()->create(['job_no' => 'JOB-00007']);
        $stockOut = app(StockOutService::class)->issue([
            'location' => 'stores', 'recipient' => 'Saman', 'reason' => 'job', 'job_id' => $job->id,
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ], $admin);

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->putJson(route('stock-out.update', $stockOut), ['recipient' => 'X', 'reason' => 'sale'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson(route('stock-out.update', $stockOut), [
                'recipient' => 'Ruwan',
                'reason' => 'other',
                'reason_detail' => 'Demo unit',
                'job_id' => $job->id,
                'note' => 'Corrected',
                'location' => 'showroom',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'SO-00001 updated.');

        $stockOut->refresh();
        $this->assertSame(['Ruwan', 'other', 'Demo unit', null, null, 'Corrected', 'stores'], [
            $stockOut->recipient, $stockOut->reason, $stockOut->reason_detail, $stockOut->job_id, $stockOut->job_no, $stockOut->note, $stockOut->location,
        ]);

        $movement = StockMovement::query()->where('reference_type', 'stock_out')->sole();
        $this->assertSame(['Ruwan', 'other', 'Demo unit', null, -2], [$movement->recipient, $movement->reason, $movement->reason_detail, $movement->job_no, $movement->qty]);

        $log = AuditLog::query()->sole();
        $this->assertSame(['stock_outs', 'SO-00001'], [$log->collection_name, $log->label]);
        $this->assertSame([
            ['field' => 'recipient', 'before' => 'Saman', 'after' => 'Ruwan'],
            ['field' => 'reason', 'before' => 'Job / Repair', 'after' => 'Other'],
            ['field' => 'reason_detail', 'before' => null, 'after' => 'Demo unit'],
            ['field' => 'job', 'before' => 'JOB-00007', 'after' => null],
            ['field' => 'note', 'before' => null, 'after' => 'Corrected'],
        ], $log->changes);

        $this->actingAs($admin)
            ->putJson(route('stock-out.update', $stockOut), ['recipient' => 'Ruwan', 'reason' => 'other', 'reason_detail' => 'Demo unit', 'note' => 'Corrected'])
            ->assertJsonPath('message', 'Nothing changed.');

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_the_job_picker_finds_jobs_by_number_name_or_phone(): void
    {
        Job::factory()->create(['job_no' => 'JOB-00123', 'customer_name' => 'Amal Perera', 'customer_phone' => '0771234567']);
        Job::factory()->create(['job_no' => 'JOB-00124', 'customer_name' => 'Bimal Silva', 'customer_phone' => '0719999999']);
        $manager = User::factory()->create(['role' => 'Manager']);

        $this->actingAs($manager)->getJson(route('api.jobs.search', ['q' => '123']))
            ->assertOk()
            ->assertJsonPath('*.label', ['JOB-00123']);

        $this->actingAs($manager)->getJson(route('api.jobs.search', ['q' => 'bimal']))->assertJsonPath('*.label', ['JOB-00124']);
        $this->actingAs($manager)->getJson(route('api.jobs.search', ['q' => '0771']))->assertJsonPath('*.label', ['JOB-00123']);
        $this->actingAs($manager)->getJson(route('api.jobs.search'))->assertJsonCount(2);
    }
}
