<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_transfer_consumes_stores_batches_fifo_and_opens_a_matching_showroom_batch_for_each(): void
    {
        $product = Product::factory()->create(['name' => 'Logitech Mouse']);
        $older = $this->receiveStock($product, 2, 100, 150, now()->subDays(5));
        $newer = $this->receiveStock($product, 3, 120, null, now()->subDay());
        $manager = User::factory()->create(['role' => 'Manager', 'name' => 'Nimal']);

        $this->actingAs($manager)
            ->postJson(route('stock-transfer.store'), [
                'note' => 'Display shelf',
                'items' => [['product_id' => $product->id, 'qty' => 4]],
            ])
            ->assertCreated()
            ->assertJsonPath('redirect', route('stock-transfer.index'));

        $transfer = StockTransfer::query()->sole();
        $this->assertSame(['TRF-00001', 'Nimal', 'Display shelf'], [$transfer->transfer_no, $transfer->transferred_by_name, $transfer->note]);

        $product->refresh();
        $this->assertSame([1, 4, 5], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);

        $this->assertSame([0, 'depleted'], [$older->fresh()->remaining_qty, $older->fresh()->status]);
        $this->assertSame([1, 'active'], [$newer->fresh()->remaining_qty, $newer->fresh()->status]);

        $showroomBatches = $product->batches()->where('location', 'showroom')->orderBy('id')->get();
        $this->assertSame([
            [$older->id, 2, 2, '100.00', '150.00', 'Transferred via TRF-00001'],
            [$newer->id, 2, 2, '120.00', null, 'Transferred via TRF-00001'],
        ], $showroomBatches->map(fn (ProductBatch $batch): array => [
            $batch->source_batch_id, $batch->total_qty, $batch->remaining_qty, $batch->cost_price, $batch->selling_price, $batch->note,
        ])->all());

        $item = $transfer->items()->sole();
        $this->assertSame([4, [$older->id, $newer->id], $showroomBatches->pluck('id')->all(), []], [
            $item->qty, $item->source_batch_ids, $item->new_batch_ids, $item->serial_numbers,
        ]);

        $movement = StockMovement::query()->where('reference_type', 'transfer')->sole();
        $this->assertSame(['transfer', 4, $transfer->id, 'stores', 'showroom', 'Nimal'], [
            $movement->type, $movement->qty, $movement->reference_id, $movement->from_location, $movement->to_location, $movement->performed_by_name,
        ]);
    }

    public function test_serial_units_move_to_showroom_with_new_batches(): void
    {
        $product = Product::factory()->serialTracked()->create();
        $first = $this->receiveStock($product, 2, 50000, null, now()->subDays(2), ['LP-1', 'LP-2']);
        $second = $this->receiveStock($product, 1, 52000, 60000, now()->subDay(), ['LP-3']);
        $picked = $product->units()->whereIn('serial_number', ['LP-1', 'LP-3'])->pluck('id')->all();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('stock-transfer.store'), ['items' => [['product_id' => $product->id, 'unit_ids' => $picked]]])
            ->assertCreated();

        $product->refresh();
        $this->assertSame([1, 2, 3], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertSame([1, 0], [$first->fresh()->remaining_qty, $second->fresh()->remaining_qty]);

        $moved = $product->units()->whereIn('id', $picked)->with('batch')->orderBy('id')->get();
        $this->assertSame(['showroom', 'showroom'], $moved->pluck('location')->all());
        $this->assertSame([$first->id, $second->id], $moved->pluck('batch.source_batch_id')->all());
        $this->assertSame(['showroom', 'showroom'], $moved->pluck('batch.location')->all());
        $this->assertSame('60000.00', $moved[1]->batch->selling_price);
        $this->assertSame('stores', $product->units()->where('serial_number', 'LP-2')->value('location'));

        $this->assertSame(['LP-1', 'LP-3'], StockTransfer::query()->sole()->items()->sole()->serial_numbers);
    }

    public function test_a_transfer_cannot_take_more_than_stores_holds_or_units_already_in_showroom(): void
    {
        $product = Product::factory()->create(['name' => 'HDMI Cable']);
        $this->receiveStock($product, 1, 100, null, now());
        $serialProduct = Product::factory()->serialTracked()->create();
        $this->receiveStock($serialProduct, 1, 100, null, now(), ['SN-STORES']);
        $this->receiveStock($serialProduct, 1, 100, null, now(), ['SN-SHOWROOM'], 'showroom');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('stock-transfer.store'), ['items' => [['product_id' => $product->id, 'qty' => 2]]])
            ->assertJsonValidationErrors(['items.0.qty' => 'Only 1 of "HDMI Cable" in Stores.']);

        $this->actingAs($admin)
            ->postJson(route('stock-transfer.store'), ['items' => [
                ['product_id' => $product->id],
                ['product_id' => $serialProduct->id, 'unit_ids' => []],
            ]])
            ->assertJsonValidationErrors(['items.0.qty' => 'Enter the quantity.', 'items.1.unit_ids' => 'Pick the serial numbers.']);

        $this->actingAs($admin)
            ->postJson(route('stock-transfer.store'), ['items' => [
                ['product_id' => $serialProduct->id, 'unit_ids' => [$serialProduct->units()->where('serial_number', 'SN-SHOWROOM')->value('id')]],
            ]])
            ->assertJsonValidationErrors(['stock' => '"SN-SHOWROOM" is no longer in stock in Stores.']);

        $this->assertDatabaseCount('stock_transfers', 0);
        $this->assertSame([1, 0], [$product->fresh()->stores_stock, $product->fresh()->showroom_stock]);
    }

    public function test_creating_a_transfer_needs_the_create_permission(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);
        $product = Product::factory()->create();
        $this->receiveStock($product, 3, 100, null, now());

        $this->actingAs($staff)->get(route('stock-transfer.index'))->assertOk()->assertDontSee('New Transfer');
        $this->actingAs($staff)->get(route('stock-transfer.create'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($staff)
            ->postJson(route('stock-transfer.store'), ['items' => [['product_id' => $product->id, 'qty' => 1]]])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->get(route('stock-transfer.create'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_list_defaults_to_the_last_30_days_and_shows_a_transfer(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Kasun']);
        $product = Product::factory()->create();
        $this->receiveStock($product, 5, 100, null, now());
        $service = app(StockTransferService::class);
        $recent = $service->transfer(['items' => [['product_id' => $product->id, 'qty' => 1]]], $admin);
        $old = $service->transfer(['items' => [['product_id' => $product->id, 'qty' => 2]]], $admin);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get(route('stock-transfer.index'))
            ->assertOk()
            ->assertSee('TRF-00001')
            ->assertDontSee('TRF-00002');

        $this->actingAs($admin)->get(route('stock-transfer.index', ['search' => 'kasun', 'from' => now()->subDays(60)->toDateString()]))
            ->assertSee(['TRF-00001', 'TRF-00002']);

        $this->actingAs($admin)->getJson(route('stock-transfer.show', $recent))
            ->assertOk()
            ->assertJsonPath('record.number', 'TRF-00001')
            ->assertJsonPath('record.items.0.qty', 1);
    }

    public function test_admin_edit_changes_the_note_only_and_is_audit_logged(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin User']);
        $product = Product::factory()->create();
        $this->receiveStock($product, 2, 100, null, now());
        $transfer = app(StockTransferService::class)->transfer(['note' => 'Old', 'items' => [['product_id' => $product->id, 'qty' => 2]]], $admin);

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->putJson(route('stock-transfer.update', $transfer), ['note' => 'x'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson(route('stock-transfer.update', $transfer), ['note' => 'New note', 'items' => [['qty' => 99]]])
            ->assertOk()
            ->assertJsonPath('message', 'TRF-00001 updated.')
            ->assertJsonPath('record.note', 'New note');

        $this->assertSame(2, $transfer->items()->sole()->qty);

        $log = AuditLog::query()->sole();
        $this->assertSame(['stock_transfers', 'TRF-00001', 'Admin User'], [$log->collection_name, $log->label, $log->performed_by_name]);
        $this->assertSame([['field' => 'note', 'before' => 'Old', 'after' => 'New note']], $log->changes);

        $this->actingAs($admin)
            ->putJson(route('stock-transfer.update', $transfer), ['note' => ' New note '])
            ->assertJsonPath('message', 'Nothing changed.');

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_the_serial_picker_lists_in_stock_units_at_one_location(): void
    {
        $product = Product::factory()->serialTracked()->create();
        $this->receiveStock($product, 2, 100, null, now(), ['S-1', 'S-2']);
        $this->receiveStock($product, 1, 100, null, now(), ['R-1'], 'showroom');

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->getJson(route('api.products.units', [$product, 'location' => 'stores']))
            ->assertOk()
            ->assertJsonPath('units.*.serial_number', ['S-1', 'S-2']);
    }
}
