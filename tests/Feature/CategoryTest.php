<?php

namespace Tests\Feature;

use App\Models\MainCategory;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_main_categories_with_their_subcategories(): void
    {
        $laptops = MainCategory::factory()->create(['name' => 'Laptops']);
        SubCategory::factory()->for($laptops)->create(['name' => 'Gaming Laptops']);

        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Laptops', 'Gaming Laptops'])
            ->assertSee('Add Main Category')
            ->assertSee('Add Subcategory');
    }

    public function test_staff_can_add_and_edit_main_and_sub_categories(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($staff)
            ->postJson(route('categories.main.store'), ['name' => 'Printers'])
            ->assertCreated();

        $printers = MainCategory::query()->where('name', 'Printers')->sole();

        $this->actingAs($staff)
            ->postJson(route('categories.sub.store'), ['main_category_id' => $printers->id, 'name' => 'Laser'])
            ->assertCreated();

        $laser = $printers->subCategories()->sole();

        $this->actingAs($staff)
            ->putJson(route('categories.main.update', $printers), ['name' => 'Printers & Scanners'])
            ->assertOk();
        $this->actingAs($staff)
            ->putJson(route('categories.sub.update', $laser), ['main_category_id' => $printers->id, 'name' => 'Laser Printers'])
            ->assertOk();

        $this->assertSame('Printers & Scanners', $printers->fresh()->name);
        $this->assertSame('Laser Printers', $laser->fresh()->name);
    }

    public function test_subcategory_names_are_unique_within_their_main_category_only(): void
    {
        $laptops = MainCategory::factory()->create();
        $desktops = MainCategory::factory()->create();
        SubCategory::factory()->for($laptops)->create(['name' => 'Accessories']);
        $user = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($user)
            ->postJson(route('categories.sub.store'), ['main_category_id' => $laptops->id, 'name' => 'Accessories'])
            ->assertJsonValidationErrors(['name' => 'This main category already has a subcategory with this name.']);

        $this->actingAs($user)
            ->postJson(route('categories.sub.store'), ['main_category_id' => $desktops->id, 'name' => 'Accessories'])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('categories.sub.store'), ['name' => 'Orphan'])
            ->assertJsonValidationErrors(['main_category_id' => 'Choose a main category.']);
    }

    public function test_deleting_a_main_category_deletes_its_subcategories(): void
    {
        $mainCategory = MainCategory::factory()->create(['name' => 'Laptops']);
        $subCategory = SubCategory::factory()->for($mainCategory)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('categories.index'))
            ->assertSee("Its 1 subcategory ({$subCategory->name}) will be deleted too.", false);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('categories.main.destroy', $mainCategory))
            ->assertRedirect(route('categories.index'))
            ->assertSessionHas('status', 'Main category "Laptops" and its subcategories deleted.');

        $this->assertModelMissing($mainCategory);
        $this->assertModelMissing($subCategory);
    }

    public function test_delete_requires_the_categories_delete_permission(): void
    {
        $subCategory = SubCategory::factory()->create();
        $manager = User::factory()->create(['role' => 'Manager']);

        $this->actingAs($manager)->delete(route('categories.sub.destroy', $subCategory))->assertForbidden();
        $this->actingAs($manager)->delete(route('categories.main.destroy', $subCategory->main_category_id))->assertForbidden();

        $this->assertModelExists($subCategory);
    }

    public function test_categories_used_by_products_are_not_deleted_or_moved(): void
    {
        $product = Product::factory()->create();
        $subCategory = $product->subCategory;
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('categories.sub.destroy', $subCategory))
            ->assertSessionHas('error', "\"{$subCategory->name}\" is used by 1 product and can't be deleted.");

        $this->actingAs($admin)
            ->delete(route('categories.main.destroy', $subCategory->main_category_id))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->putJson(route('categories.sub.update', $subCategory), [
                'main_category_id' => MainCategory::factory()->create()->id,
                'name' => $subCategory->name,
            ])
            ->assertJsonValidationErrors('main_category_id');

        $this->assertModelExists($subCategory);
        $this->assertSame($product->main_category_id, $subCategory->fresh()->main_category_id);
    }
}
