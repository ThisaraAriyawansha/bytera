<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_list_add_and_edit_brands(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);
        Brand::factory()->create(['name' => 'Lenovo']);

        $this->actingAs($staff)
            ->get(route('brands.index'))
            ->assertOk()
            ->assertSee('Lenovo')
            ->assertSee('Add Brand')
            ->assertDontSee('Delete brand?');

        $this->actingAs($staff)
            ->postJson(route('brands.store'), ['name' => '  HP ', 'description' => 'Laptops and printers'])
            ->assertCreated();

        $brand = Brand::query()->where('name', 'HP')->sole();
        $this->assertSame('Laptops and printers', $brand->description);

        $this->actingAs($staff)
            ->putJson(route('brands.update', $brand), ['name' => 'HP Inc.', 'description' => ''])
            ->assertOk();

        $brand->refresh();
        $this->assertSame('HP Inc.', $brand->name);
        $this->assertNull($brand->description);
    }

    public function test_brand_names_are_unique(): void
    {
        $brand = Brand::factory()->create(['name' => 'Dell']);
        $user = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($user)
            ->postJson(route('brands.store'), ['name' => 'Dell'])
            ->assertJsonValidationErrors(['name' => 'A brand with this name already exists.']);

        $this->actingAs($user)
            ->putJson(route('brands.update', $brand), ['name' => 'Dell'])
            ->assertOk();
    }

    public function test_delete_requires_the_brands_delete_permission(): void
    {
        $brand = Brand::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'Manager']))
            ->delete(route('brands.destroy', $brand))
            ->assertForbidden();

        $this->assertModelExists($brand);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('brands.destroy', $brand))
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('status');

        $this->assertModelMissing($brand);
    }

    public function test_brand_used_by_a_product_is_not_deleted(): void
    {
        $brand = Brand::factory()->create(['name' => 'Asus']);
        Product::factory()->for($brand)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('brands.destroy', $brand))
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('error', "\"Asus\" is used by 1 product and can't be deleted.");

        $this->assertModelExists($brand);
    }

    public function test_users_without_brands_view_are_restricted(): void
    {
        $user = User::factory()->create([
            'role' => 'Staff',
            'permissions' => [...Permissions::defaults('Staff'), 'brands.view' => false],
        ]);

        $this->actingAs($user)->get(route('brands.index'))->assertForbidden()->assertSee('Access Restricted');
        $this->actingAs($user)->postJson(route('brands.store'), ['name' => 'Acer'])->assertForbidden();

        $this->assertDatabaseCount('brands', 0);
    }
}
