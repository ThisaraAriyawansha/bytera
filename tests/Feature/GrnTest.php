<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Grn;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GrnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrnTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_a_grn_adds_stock_to_stores_and_paying_the_supplier_reduces_what_we_owe(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Tech Distributors']);
        $mouse = Product::factory()->create(['showroom_stock' => 1, 'total_stock' => 1, 'low_stock_alerted' => true]);
        $laptop = Product::factory()->serialTracked()->create();
        $manager = User::factory()->create(['role' => 'Manager', 'name' => 'Nimal']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($manager)
            ->postJson(route('grn.store'), [
                'supplier_id' => $supplier->id,
                'location' => 'stores',
                'note' => 'INV-7781',
                'items' => [
                    ['product_id' => $mouse->id, 'cost_price' => 1000, 'selling_price' => '', 'qty' => 5],
                    ['product_id' => $laptop->id, 'cost_price' => 2500.5, 'selling_price' => 4000, 'serials' => [' LP-1 ', 'LP-2']],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('redirect', route('grn.index'));

        $grn = Grn::query()->sole();
        $this->assertSame(['GRN-00001', 'Tech Distributors', '10001.00', 'stores', 'INV-7781', 'Nimal'], [
            $grn->grn_no, $grn->supplier_name, $grn->total_cost, $grn->location, $grn->note, $grn->received_by_name,
        ]);

        $mouse->refresh();
        $laptop->refresh();
        $this->assertSame([5, 1, 6], [$mouse->stores_stock, $mouse->showroom_stock, $mouse->total_stock]);
        $this->assertFalse($mouse->low_stock_alerted);
        $this->assertSame([2, 0, 2], [$laptop->stores_stock, $laptop->showroom_stock, $laptop->total_stock]);

        $laptopBatch = $laptop->batches()->sole();
        $this->assertSame(['stores', 2, '2500.50', '4000.00', $supplier->id, 'Received via GRN-00001'], [
            $laptopBatch->location, $laptopBatch->remaining_qty, $laptopBatch->cost_price, $laptopBatch->selling_price, $laptopBatch->supplier_id, $laptopBatch->note,
        ]);
        $this->assertSame(['LP-1', 'LP-2'], $laptopBatch->units()->where('status', 'in_stock')->where('location', 'stores')->pluck('serial_number')->all());
        $this->assertNull($mouse->batches()->sole()->selling_price);
        $this->assertSame(['LP-1', 'LP-2'], $grn->items()->where('product_id', $laptop->id)->sole()->serials);

        $movements = StockMovement::query()->where('reference_type', 'grn')->where('reference_id', $grn->id)->orderBy('id')->get();
        $this->assertSame([5, 2], $movements->pluck('qty')->all());
        $this->assertSame(['in'], $movements->pluck('type')->unique()->values()->all());
        $this->assertSame(['Tech Distributors'], $movements->pluck('supplier_name')->unique()->values()->all());
        $this->assertSame(['stores'], $movements->pluck('location')->unique()->values()->all());

        $supplier->refresh();
        $this->assertSame(['10001.00', '0.00', '10001.00', 'outstanding'], [
            $supplier->total_payable, $supplier->amount_paid, $supplier->balance, $supplier->payment_status,
        ]);

        $this->actingAs($admin)
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 4000, 'method' => 'cash'])
            ->assertCreated();

        $supplier->refresh();
        $this->assertSame(['10001.00', '4000.00', '6001.00', 'partial'], [
            $supplier->total_payable, $supplier->amount_paid, $supplier->balance, $supplier->payment_status,
        ]);

        $this->actingAs($admin)
            ->postJson(route('suppliers.payments.store', $supplier), ['amount' => 6001, 'method' => 'bank_transfer'])
            ->assertCreated();

        $this->assertSame(['0.00', 'paid'], [$supplier->fresh()->balance, $supplier->fresh()->payment_status]);
    }

    public function test_a_second_grn_to_a_partly_paid_supplier_keeps_the_balance_running(): void
    {
        $supplier = Supplier::factory()->owing(10000, 10000)->create();
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('grn.store'), [
                'supplier_id' => $supplier->id,
                'location' => 'stores',
                'items' => [['product_id' => $product->id, 'cost_price' => 500, 'qty' => 4]],
            ])
            ->assertCreated();

        $supplier->refresh();
        $this->assertSame(['12000.00', '10000.00', '2000.00', 'partial'], [
            $supplier->total_payable, $supplier->amount_paid, $supplier->balance, $supplier->payment_status,
        ]);
    }

    public function test_a_showroom_grn_without_a_supplier_owes_nobody(): void
    {
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('grn.store'), [
                'supplier_id' => '',
                'location' => 'showroom',
                'items' => [['product_id' => $product->id, 'cost_price' => 750, 'qty' => 3]],
            ])
            ->assertCreated();

        $product->refresh();
        $this->assertSame([0, 3, 3], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertSame('showroom', $product->batches()->sole()->location);
        $this->assertSame(['', null, '2250.00'], [Grn::query()->sole()->supplier_name, Grn::query()->sole()->supplier_id, Grn::query()->sole()->total_cost]);
        $this->assertSame('0.00', $supplier->fresh()->total_payable);
    }

    public function test_items_need_a_positive_cost_and_a_quantity_or_new_unique_serials(): void
    {
        $product = Product::factory()->create();
        $serialProduct = Product::factory()->serialTracked()->create();
        $existingBatch = ProductBatch::query()->create([
            'product_id' => $serialProduct->id, 'cost_price' => 1, 'total_qty' => 1, 'remaining_qty' => 1,
            'status' => 'active', 'location' => 'stores', 'note' => '', 'received_at' => now(),
        ]);
        $serialProduct->units()->create([
            'batch_id' => $existingBatch->id, 'serial_number' => 'SN-OLD', 'cost_price' => 1, 'status' => 'in_stock', 'location' => 'stores',
        ]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('grn.store'), ['location' => 'stores', 'items' => []])
            ->assertJsonValidationErrors(['items' => 'Add at least one item.']);

        $this->actingAs($admin)
            ->postJson(route('grn.store'), [
                'location' => 'stores',
                'items' => [
                    ['product_id' => $product->id, 'cost_price' => 0, 'qty' => 1],
                    ['product_id' => $product->id, 'cost_price' => 10, 'qty' => 0],
                ],
            ])
            ->assertJsonValidationErrors([
                'items.0.cost_price' => 'The cost price must be greater than 0.',
                'items.1.qty' => 'The quantity must be at least 1.',
            ]);

        $this->actingAs($admin)
            ->postJson(route('grn.store'), [
                'location' => 'stores',
                'items' => [
                    ['product_id' => $product->id, 'cost_price' => 10],
                    ['product_id' => $serialProduct->id, 'cost_price' => 10, 'qty' => 2],
                ],
            ])
            ->assertJsonValidationErrors([
                'items.0.qty' => 'Enter the quantity received.',
                'items.1.serials' => 'Add a serial number for every unit.',
            ]);

        $this->actingAs($admin)
            ->postJson(route('grn.store'), [
                'location' => 'stores',
                'items' => [
                    ['product_id' => $serialProduct->id, 'cost_price' => 10, 'serials' => ['SN-OLD', 'SN-NEW']],
                    ['product_id' => $serialProduct->id, 'cost_price' => 10, 'serials' => ['sn-new']],
                ],
            ])
            ->assertJsonValidationErrors([
                'items.0.serials.0' => 'Serial "SN-OLD" already exists for this product.',
                'items.1.serials.0' => 'Serial "sn-new" is entered twice.',
            ]);

        $this->assertDatabaseCount('grns', 0);
        $this->assertSame(0, $product->fresh()->total_stock);
    }

    public function test_creating_a_grn_needs_the_create_permission(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);
        $product = Product::factory()->create();

        $this->actingAs($staff)->get(route('grn.index'))->assertOk()->assertDontSee('New GRN');
        $this->actingAs($staff)->get(route('grn.create'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($staff)
            ->postJson(route('grn.store'), ['location' => 'stores', 'items' => [['product_id' => $product->id, 'cost_price' => 1, 'qty' => 1]]])
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->get(route('grn.create'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_list_defaults_to_the_last_30_days_and_searches_number_or_supplier(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);
        $recent = $this->grn(['grn_no' => 'GRN-00002', 'supplier_name' => 'Acme Traders']);
        $this->grn(['grn_no' => 'GRN-00003', 'supplier_name' => 'Beta Imports']);
        $old = $this->grn(['grn_no' => 'GRN-00001', 'supplier_name' => 'Old Supplier']);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->actingAs($user)->get(route('grn.index'))
            ->assertOk()
            ->assertSee(['GRN-00002', 'GRN-00003'])
            ->assertDontSee('GRN-00001');

        $this->actingAs($user)->get(route('grn.index', ['search' => 'acme']))
            ->assertSee('GRN-00002')
            ->assertDontSee('GRN-00003');

        $this->actingAs($user)->get(route('grn.index', ['from' => now()->subDays(60)->toDateString()]))
            ->assertSee('GRN-00001');

        $this->actingAs($user)->getJson(route('grn.show', $recent))
            ->assertOk()
            ->assertJsonPath('grn.grn_no', 'GRN-00002');
    }

    public function test_admin_edit_changes_prices_and_batch_cost_without_recomputing_totals_and_is_audit_logged(): void
    {
        $original = Supplier::factory()->create(['name' => 'Original Supplier']);
        $other = Supplier::factory()->create(['name' => 'Other Supplier']);
        $product = Product::factory()->create();
        $admin = User::factory()->admin()->create(['name' => 'Admin User']);

        $grn = app(GrnService::class)->receive([
            'supplier_id' => $original->id,
            'location' => 'stores',
            'note' => 'INV-1',
            'items' => [['product_id' => $product->id, 'cost_price' => 1000, 'selling_price' => null, 'qty' => 2]],
        ], $admin);
        $item = $grn->items()->sole();

        $this->actingAs($admin)
            ->putJson(route('grn.update', $grn), [
                'supplier_id' => $other->id,
                'note' => 'INV-1A',
                'items' => [['id' => $item->id, 'cost_price' => 900, 'selling_price' => 1500]],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'GRN-00001 updated.');

        $grn->refresh();
        $this->assertSame([$other->id, 'Other Supplier', 'INV-1A', '2000.00'], [$grn->supplier_id, $grn->supplier_name, $grn->note, $grn->total_cost]);
        $this->assertSame(['900.00', '1500.00'], [$item->fresh()->cost_price, $item->fresh()->selling_price]);
        $this->assertSame('900.00', $item->batch->fresh()->cost_price);
        $this->assertSame('2000.00', $original->fresh()->total_payable);
        $this->assertSame('0.00', $other->fresh()->total_payable);

        $log = AuditLog::query()->sole();
        $this->assertSame(['grns', (string) $grn->id, 'GRN-00001', 'Admin User'], [$log->collection_name, $log->doc_id, $log->label, $log->performed_by_name]);
        $this->assertSame(
            ['supplier', 'note', "{$product->name} · cost_price", "{$product->name} · selling_price"],
            array_column($log->changes, 'field'),
        );
        $this->assertSame(['Original Supplier', 'Other Supplier'], [$log->changes[0]['before'], $log->changes[0]['after']]);

        $this->actingAs($admin)
            ->putJson(route('grn.update', $grn), [
                'supplier_id' => $other->id,
                'note' => 'INV-1A',
                'items' => [['id' => $item->id, 'cost_price' => '900.00', 'selling_price' => '1500']],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Nothing changed.');

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_admin_edit_needs_the_edit_permission_and_only_accepts_its_own_items(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $service = app(GrnService::class);
        $grn = $service->receive(['location' => 'stores', 'items' => [['product_id' => $product->id, 'cost_price' => 10, 'qty' => 1]]], $admin);
        $otherGrn = $service->receive(['location' => 'stores', 'items' => [['product_id' => $product->id, 'cost_price' => 10, 'qty' => 1]]], $admin);

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->putJson(route('grn.update', $grn), ['note' => 'x'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->putJson(route('grn.update', $grn), ['items' => [['id' => $otherGrn->items()->sole()->id, 'cost_price' => 5]]])
            ->assertJsonValidationErrors('items.0.id');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    /**
     * Create a GRN row directly, for list tests that don't need stock.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function grn(array $attributes): Grn
    {
        $user = User::factory()->create();

        return Grn::query()->create([
            'supplier_name' => '',
            'total_cost' => 100,
            'received_by_id' => $user->id,
            'received_by_name' => $user->name,
            'note' => '',
            'location' => 'stores',
            ...$attributes,
        ]);
    }
}
