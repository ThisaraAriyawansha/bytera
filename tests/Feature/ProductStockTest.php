<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUnit;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_batches_modal_lists_batches_with_the_product_counters(): void
    {
        $product = Product::factory()->create(['stores_stock' => 4, 'showroom_stock' => 1, 'total_stock' => 5]);
        $this->batch($product, ['remaining_qty' => 4, 'total_qty' => 6, 'cost_price' => 900, 'selling_price' => 1500]);
        $this->batch($product, ['location' => 'showroom', 'remaining_qty' => 1, 'total_qty' => 1, 'received_at' => now()->addMinute()]);

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->getJson(route('products.batches.index', $product))
            ->assertOk()
            ->assertJsonPath('product.total_stock', 5)
            ->assertJsonPath('product.stores_stock', 4)
            ->assertJsonPath('product.showroom_stock', 1)
            ->assertJsonCount(2, 'batches')
            ->assertJsonPath('batches.0.location', 'showroom')
            ->assertJsonPath('batches.1.remaining_qty', 4)
            ->assertJsonPath('batches.1.total_qty', 6)
            ->assertJsonPath('batches.1.cost_price', 900)
            ->assertJsonPath('batches.1.selling_price', 1500);
    }

    public function test_adding_a_batch_lands_in_stores_and_resets_the_low_stock_alert(): void
    {
        $product = Product::factory()->create(['showroom_stock' => 1, 'total_stock' => 1, 'low_stock_alerted' => true]);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('products.batches.store', $product), ['cost_price' => 700, 'selling_price' => '', 'qty' => 5, 'note' => 'Walk-in supplier'])
            ->assertCreated();

        $product->refresh();
        $batch = $product->batches()->sole();

        $this->assertSame([5, 1, 6], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertFalse($product->low_stock_alerted);
        $this->assertSame(['stores', 5, null, 'Walk-in supplier'], [$batch->location, $batch->remaining_qty, $batch->selling_price, $batch->note]);
        $this->assertSame(5, StockMovement::query()->sole()->qty);
    }

    public function test_adding_a_serial_batch_needs_new_serials(): void
    {
        $product = Product::factory()->serialTracked()->create();
        $batch = $this->batch($product);
        $this->unit($product, $batch, 'SN-1');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('products.batches.store', $product), ['cost_price' => 10, 'serials' => ['SN-1', 'SN-2']])
            ->assertJsonValidationErrors(['serials.0' => 'Serial "SN-1" already exists for this product.']);

        $this->actingAs($admin)
            ->postJson(route('products.batches.store', $product), ['cost_price' => 10, 'serials' => ['SN-2', 'SN-3']])
            ->assertCreated();

        $this->assertSame(2, $product->fresh()->stores_stock);
        $this->assertSame(2, $product->batches()->latest('id')->first()->units()->count());
    }

    public function test_editing_a_batch_quantity_corrects_the_counters_and_logs_an_adjustment(): void
    {
        $product = Product::factory()->create(['showroom_stock' => 5, 'total_stock' => 5]);
        $batch = $this->batch($product, ['location' => 'showroom', 'total_qty' => 5, 'remaining_qty' => 5]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('products.batches.update', [$product, $batch]), [
                'cost_price' => 950, 'selling_price' => 1400, 'total_qty' => 5, 'remaining_qty' => 3, 'note' => 'Recount',
            ])
            ->assertOk();

        $product->refresh();
        $batch->refresh();
        $movement = StockMovement::query()->sole();

        $this->assertSame([0, 3, 3], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertSame(['950.00', '1400.00', 3, 'Recount'], [$batch->cost_price, $batch->selling_price, $batch->remaining_qty, $batch->note]);
        $this->assertSame(['adjustment', -2, 'batch_edit', 'Batch quantity corrected', 'showroom'], [
            $movement->type, $movement->qty, $movement->reference_type, $movement->note, $movement->location,
        ]);
    }

    public function test_emptying_a_batch_marks_it_depleted_and_price_only_edits_log_nothing(): void
    {
        $product = Product::factory()->create(['stores_stock' => 2, 'total_stock' => 2]);
        $batch = $this->batch($product, ['total_qty' => 2, 'remaining_qty' => 2]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson(route('products.batches.update', [$product, $batch]), ['cost_price' => 5, 'total_qty' => 2, 'remaining_qty' => 2])
            ->assertOk();
        $this->assertDatabaseCount('stock_movements', 0);

        $this->actingAs($admin)
            ->putJson(route('products.batches.update', [$product, $batch]), ['cost_price' => 5, 'total_qty' => 2, 'remaining_qty' => 0])
            ->assertOk();
        $this->assertSame('depleted', $batch->fresh()->status);

        $this->actingAs($admin)
            ->putJson(route('products.batches.update', [$product, $batch]), ['cost_price' => 5, 'total_qty' => 2, 'remaining_qty' => 3])
            ->assertJsonValidationErrors(['remaining_qty' => 'Remaining can\'t be more than the total quantity.']);
    }

    public function test_serial_batch_quantities_cannot_be_edited_directly(): void
    {
        $product = Product::factory()->serialTracked()->create(['stores_stock' => 1, 'total_stock' => 1]);
        $batch = $this->batch($product, ['total_qty' => 1, 'remaining_qty' => 1]);
        $unit = $this->unit($product, $batch, 'SN-1');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson(route('products.batches.update', [$product, $batch]), ['cost_price' => 5, 'total_qty' => 1, 'remaining_qty' => 0])
            ->assertJsonValidationErrors('remaining_qty');

        $this->actingAs($admin)
            ->putJson(route('products.batches.update', [$product, $batch]), ['cost_price' => 777, 'total_qty' => 1, 'remaining_qty' => 1])
            ->assertOk();

        $this->assertSame('777.00', $unit->fresh()->cost_price);
    }

    public function test_serials_modal_lists_adds_and_edits_units(): void
    {
        $product = Product::factory()->serialTracked()->create(['stores_stock' => 1, 'total_stock' => 1]);
        $batch = $this->batch($product, ['total_qty' => 1, 'remaining_qty' => 1]);
        $unit = $this->unit($product, $batch, 'SN-1');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('products.units.store', [$product, $batch]), ['serials' => ['SN-2', ' SN-3 ']])
            ->assertCreated();

        $this->actingAs($admin)
            ->putJson(route('products.units.update', [$product, $unit]), ['serial_number' => 'SN-2'])
            ->assertJsonValidationErrors(['serial_number' => 'Serial "SN-2" already exists for this product.']);

        $this->actingAs($admin)
            ->putJson(route('products.units.update', [$product, $unit]), ['serial_number' => 'SN-1A'])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('products.units.index', [$product, $batch]))
            ->assertOk()
            ->assertJsonPath('batch.total_qty', 3)
            ->assertJsonPath('batch.remaining_qty', 3)
            ->assertJsonPath('product.stores_stock', 3)
            ->assertJsonPath('product.total_stock', 3)
            ->assertJsonPath('units.*.serial_number', ['SN-1A', 'SN-2', 'SN-3']);
    }

    public function test_only_in_stock_units_can_be_deleted(): void
    {
        $product = Product::factory()->serialTracked()->create(['showroom_stock' => 1, 'total_stock' => 1]);
        $batch = $this->batch($product, ['location' => 'showroom', 'total_qty' => 2, 'remaining_qty' => 1]);
        $inStock = $this->unit($product, $batch, 'SN-1');
        $sold = $this->unit($product, $batch, 'SN-2', 'sold');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson(route('products.units.destroy', [$product, $sold]))
            ->assertJsonValidationErrors(['unit' => 'Cannot remove a unit that has already been sold']);
        $this->assertModelExists($sold);

        $this->actingAs($admin)
            ->deleteJson(route('products.units.destroy', [$product, $inStock]))
            ->assertOk();

        $product->refresh();
        $batch->refresh();
        $this->assertModelMissing($inStock);
        $this->assertSame([0, 0], [$product->showroom_stock, $product->total_stock]);
        $this->assertSame([1, 0, 'depleted'], [$batch->total_qty, $batch->remaining_qty, $batch->status]);
        $this->assertSame(-1, StockMovement::query()->sole()->qty);
    }

    public function test_batch_and_unit_changes_need_their_permissions(): void
    {
        $product = Product::factory()->serialTracked()->create(['stores_stock' => 1, 'total_stock' => 1]);
        $batch = $this->batch($product, ['total_qty' => 1, 'remaining_qty' => 1]);
        $unit = $this->unit($product, $batch, 'SN-1');
        $staff = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($staff)->getJson(route('products.batches.index', $product))->assertOk();
        $this->actingAs($staff)->getJson(route('products.units.index', [$product, $batch]))->assertOk();
        $this->actingAs($staff)->postJson(route('products.batches.store', $product), ['cost_price' => 1, 'serials' => ['X']])->assertForbidden();
        $this->actingAs($staff)->putJson(route('products.batches.update', [$product, $batch]), ['cost_price' => 1, 'total_qty' => 1, 'remaining_qty' => 1])->assertForbidden();
        $this->actingAs($staff)->postJson(route('products.units.store', [$product, $batch]), ['serials' => ['X']])->assertForbidden();
        $this->actingAs($staff)->putJson(route('products.units.update', [$product, $unit]), ['serial_number' => 'X'])->assertForbidden();
        $this->actingAs($staff)->deleteJson(route('products.units.destroy', [$product, $unit]))->assertForbidden();

        $this->assertModelExists($unit);
    }

    public function test_units_of_another_product_are_not_reachable(): void
    {
        $product = Product::factory()->serialTracked()->create();
        $other = Product::factory()->serialTracked()->create();
        $unit = $this->unit($other, $this->batch($other), 'SN-1');

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson(route('products.units.destroy', [$product, $unit]))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function batch(Product $product, array $attributes = []): ProductBatch
    {
        return $product->batches()->create([
            'cost_price' => 100,
            'selling_price' => null,
            'total_qty' => 1,
            'remaining_qty' => 1,
            'status' => 'active',
            'location' => 'stores',
            'note' => '',
            'received_at' => now(),
            ...$attributes,
        ]);
    }

    private function unit(Product $product, ProductBatch $batch, string $serial, string $status = 'in_stock'): ProductUnit
    {
        return $product->units()->create([
            'batch_id' => $batch->id,
            'serial_number' => $serial,
            'cost_price' => $batch->cost_price,
            'status' => $status,
            'location' => $batch->location,
        ]);
    }
}
