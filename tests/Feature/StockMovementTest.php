<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\GrnService;
use App\Services\StockOutService;
use App\Services\StockTransferService;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_grn_transfer_and_stock_out_all_show_up_with_correct_counters(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Kasun']);
        $product = Product::factory()->create(['name' => 'Wireless Mouse', 'sku' => 'MOU-001']);

        $grn = app(GrnService::class)->receive(['location' => 'stores', 'items' => [['product_id' => $product->id, 'cost_price' => 800, 'qty' => 10]]], $admin);
        app(StockTransferService::class)->transfer(['items' => [['product_id' => $product->id, 'qty' => 6]]], $admin);
        app(StockOutService::class)->issue([
            'location' => 'showroom', 'recipient' => 'Saman', 'reason' => 'other', 'reason_detail' => 'Demo',
            'items' => [['product_id' => $product->id, 'qty' => 2]],
        ], $admin);

        $product->refresh();
        $this->assertSame([4, 4, 8], [$product->stores_stock, $product->showroom_stock, $product->total_stock]);
        $this->assertSame(8, (int) $product->batches()->sum('remaining_qty'));

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get(route('stock-movements.index'))
            ->assertOk()
            ->assertSeeInOrder(['Stock Out', 'Wireless Mouse', '-2', 'Showroom', 'Saman', 'SO-00001'])
            ->assertSeeInOrder(['Transfer', '+6', 'Stores → Showroom', 'TRF-00001'])
            ->assertSeeInOrder(['GRN', '+10', 'Stores', 'GRN-00001'])
            ->assertSee(route('grn.index', ['search' => 'GRN-00001', 'from' => $grn->created_at->toDateString(), 'to' => $grn->created_at->toDateString()]));
    }

    public function test_filters_by_type_and_searches_product_sku_or_reference(): void
    {
        $admin = User::factory()->admin()->create();
        $mouse = Product::factory()->create(['name' => 'Wireless Mouse', 'sku' => 'MOU-001']);
        $cable = Product::factory()->create(['name' => 'HDMI Cable', 'sku' => 'CAB-009']);
        $grns = app(GrnService::class);
        $grns->receive(['location' => 'stores', 'items' => [['product_id' => $mouse->id, 'cost_price' => 800, 'qty' => 3]]], $admin);
        $grns->receive(['location' => 'stores', 'items' => [['product_id' => $cable->id, 'cost_price' => 300, 'qty' => 2]]], $admin);
        app(StockTransferService::class)->transfer(['items' => [['product_id' => $cable->id, 'qty' => 1]]], $admin);

        $this->actingAs($admin)->get(route('stock-movements.index', ['type' => 'transfer']))
            ->assertSee('TRF-00001')
            ->assertDontSee('GRN-00001');

        $this->actingAs($admin)->get(route('stock-movements.index', ['search' => 'mou-0']))
            ->assertSee('GRN-00001')
            ->assertDontSee('GRN-00002');

        $this->actingAs($admin)->get(route('stock-movements.index', ['search' => 'GRN-00002']))
            ->assertSee('HDMI Cable')
            ->assertDontSee('Wireless Mouse')
            ->assertDontSee('TRF-00001');

        StockMovement::query()->update(['created_at' => now()->subDays(45)]);

        $this->actingAs($admin)->get(route('stock-movements.index'))->assertSee('No stock movements in this period.');
    }

    public function test_csv_export_contains_the_filtered_movements(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Kasun']);
        $product = Product::factory()->create(['name' => 'Wireless Mouse', 'sku' => 'MOU-001']);
        app(GrnService::class)->receive(['location' => 'stores', 'items' => [['product_id' => $product->id, 'cost_price' => 800, 'qty' => 3]]], $admin);
        app(StockTransferService::class)->transfer(['items' => [['product_id' => $product->id, 'qty' => 3]]], $admin);

        $response = $this->actingAs($admin)->get(route('stock-movements.export', ['type' => 'transfer']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));

        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('Date,Type,Product,SKU,Qty,Location,By', $lines[0]);
        $this->assertStringContainsString(',Transfer,"Wireless Mouse",MOU-001,3,"Stores → Showroom",Kasun,', $lines[1]);
        $this->assertStringContainsString('TRF-00001', $lines[1]);
    }

    public function test_the_page_needs_the_view_permission(): void
    {
        $user = User::factory()->create([
            'role' => 'Staff',
            'permissions' => [...Permissions::defaults('Staff'), 'stockMovements.view' => false],
        ]);

        $this->actingAs($user)->get(route('stock-movements.index'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($user)->get(route('stock-movements.export'))->assertForbidden();
    }
}
