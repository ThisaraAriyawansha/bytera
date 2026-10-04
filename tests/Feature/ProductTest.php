<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_normal_product_is_created_with_its_initial_stock_as_a_stores_batch(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'name' => 'Kasun Perera']);

        $this->actingAs($user)
            ->postJson(route('products.store'), $this->payload([
                'name' => ' Kingston SSD 512GB ',
                'barcode' => '',
                'initial_stock' => 10,
                'cost_price' => 8500,
            ]))
            ->assertCreated();

        $product = Product::query()->sole();
        $batch = $product->batches()->sole();
        $movement = StockMovement::query()->sole();

        $this->assertSame('Kingston SSD 512GB', $product->name);
        $this->assertNull($product->barcode);
        $this->assertSame([10, 10, 0], [$product->total_stock, $product->stores_stock, $product->showroom_stock]);
        $this->assertSame(['stores', 10, 10, '8500.00', null, 'active'], [
            $batch->location, $batch->total_qty, $batch->remaining_qty, $batch->cost_price, $batch->selling_price, $batch->status,
        ]);
        $this->assertSame(['in', 10, 'batch_edit', $batch->id, 'stores', 'Kasun Perera'], [
            $movement->type, $movement->qty, $movement->reference_type, $movement->reference_id, $movement->location, $movement->performed_by_name,
        ]);
    }

    public function test_a_serial_product_gets_one_unit_per_serial(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('products.store'), $this->payload([
                'track_serial' => true,
                'initial_stock' => 2,
                'cost_price' => 150000,
                'serials' => [' SN-001 ', 'SN-002'],
            ]))
            ->assertCreated();

        $product = Product::query()->sole();

        $this->assertSame(2, $product->stores_stock);
        $this->assertSame(['SN-001', 'SN-002'], $product->units()->orderBy('id')->pluck('serial_number')->all());
        $this->assertSame(2, $product->units()->where('status', 'in_stock')->where('location', 'stores')->where('batch_id', $product->batches()->sole()->id)->count());
    }

    public function test_a_product_without_initial_stock_has_no_batch(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('products.store'), $this->payload(['initial_stock' => '', 'cost_price' => '']))
            ->assertCreated();

        $this->assertSame(0, Product::query()->sole()->total_stock);
        $this->assertDatabaseCount('product_batches', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_creating_a_product_is_validated(): void
    {
        Product::factory()->create(['sku' => 'SKU-1']);
        $otherSubCategory = SubCategory::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('products.store'), $this->payload([
                'sku' => 'SKU-1',
                'sub_category_id' => $otherSubCategory->id,
                'track_serial' => true,
                'initial_stock' => 3,
                'cost_price' => '',
                'serials' => ['A', 'a'],
            ]))
            ->assertJsonValidationErrors([
                'sku' => 'Another product already uses this SKU.',
                'sub_category_id' => 'Choose a subcategory of the selected main category.',
                'cost_price' => 'Enter the cost price for the initial stock.',
                'serials' => 'Enter one serial number per unit of initial stock.',
                'serials.1' => 'Each unit needs a different serial number.',
            ]);

        $this->assertDatabaseCount('products', 1);
    }

    public function test_editing_a_product_never_touches_its_stock(): void
    {
        $product = Product::factory()->create(['stores_stock' => 4, 'total_stock' => 4]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('products.update', $product), $this->payload([
                'name' => 'Renamed',
                'sku' => $product->sku,
                'brand_id' => $product->brand_id,
                'main_category_id' => $product->main_category_id,
                'sub_category_id' => $product->sub_category_id,
                'initial_stock' => 99,
                'active' => false,
            ]))
            ->assertOk();

        $product->refresh();
        $this->assertSame('Renamed', $product->name);
        $this->assertFalse($product->active);
        $this->assertSame(4, $product->total_stock);
        $this->assertDatabaseCount('product_batches', 0);
    }

    public function test_serial_tracking_cannot_change_once_the_product_has_batches(): void
    {
        $product = Product::factory()->create();
        $product->batches()->create([
            'cost_price' => 10, 'total_qty' => 1, 'remaining_qty' => 1, 'status' => 'active',
            'location' => 'stores', 'note' => '', 'received_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('products.update', $product), $this->payload([
                'sku' => $product->sku,
                'brand_id' => $product->brand_id,
                'main_category_id' => $product->main_category_id,
                'sub_category_id' => $product->sub_category_id,
                'track_serial' => true,
            ]))
            ->assertJsonValidationErrors('track_serial');

        $this->assertFalse($product->fresh()->track_serial);
    }

    public function test_index_lists_stock_by_location_and_searches_name_sku_or_barcode(): void
    {
        Product::factory()->create(['name' => 'Logitech Mouse', 'sku' => 'LOG-1', 'barcode' => '4901234567890', 'stores_stock' => 3, 'showroom_stock' => 2, 'total_stock' => 5]);
        Product::factory()->create(['name' => 'Dell Monitor', 'sku' => 'DEL-1']);

        $user = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($user)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Stores 3 · Showroom 2')
            ->assertSee('Dell Monitor');

        foreach (['logitech', 'LOG-1', '49012345'] as $term) {
            $this->actingAs($user)->get(route('products.index', ['search' => $term]))
                ->assertSee('Logitech Mouse')
                ->assertDontSee('Dell Monitor');
        }
    }

    public function test_index_filters_by_brand_category_and_status(): void
    {
        $keep = Product::factory()->create(['name' => 'Wanted Product']);
        Product::factory()->create(['name' => 'Other Brand Product']);
        Product::factory()->create(['name' => 'Inactive Product', 'brand_id' => $keep->brand_id, 'active' => false]);

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get(route('products.index', ['brand' => $keep->brand_id, 'category' => $keep->main_category_id, 'status' => 'active']))
            ->assertSee('Wanted Product')
            ->assertDontSee('Other Brand Product')
            ->assertDontSee('Inactive Product');
    }

    public function test_edit_and_delete_need_their_permissions(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create(['role' => 'Staff', 'permissions' => ['products.edit' => false]]);

        $this->actingAs($user)->get(route('products.index'))->assertOk()->assertDontSee('Add Product');
        $this->actingAs($user)->postJson(route('products.store'), $this->payload())->assertForbidden();
        $this->actingAs($user)->putJson(route('products.update', $product), $this->payload())->assertForbidden();
        $this->actingAs($user)->delete(route('products.destroy', $product))->assertForbidden();

        $this->assertModelExists($product);
    }

    public function test_deleting_a_product_removes_its_batches(): void
    {
        $product = Product::factory()->create();
        $product->batches()->create([
            'cost_price' => 10, 'total_qty' => 1, 'remaining_qty' => 1, 'status' => 'active',
            'location' => 'stores', 'note' => '', 'received_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($product);
        $this->assertDatabaseCount('product_batches', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $subCategory = SubCategory::factory()->create();

        return [
            'name' => 'Kingston SSD 512GB',
            'brand_id' => $overrides['brand_id'] ?? Brand::factory()->create()->id,
            'sku' => 'KS-512',
            'barcode' => null,
            'main_category_id' => $subCategory->main_category_id,
            'sub_category_id' => $subCategory->id,
            'description' => null,
            'selling_price' => 12500,
            'initial_stock' => 0,
            'cost_price' => null,
            'low_stock_alert' => 5,
            'warranty_months' => 12,
            'track_serial' => false,
            'active' => true,
            'serials' => [],
            ...$overrides,
        ];
    }
}
