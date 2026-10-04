<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use App\Support\Pagination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AppLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_super_admin_can_open_every_sidebar_page_and_profile(): void
    {
        $user = User::factory()->superAdmin()->create(['name' => 'Kasun Perera']);

        foreach ([...Navigation::links(), ['route' => 'profile.edit', 'label' => 'My Profile']] as $link) {
            $this->actingAs($user)
                ->get(route($link['route']))
                ->assertOk()
                ->assertSee('<title>M-Fixpro POS</title>', false)
                ->assertSee('KP')
                ->assertSee('Super Admin · View profile')
                ->assertSee('Sign out')
                ->assertSee('© '.now()->year.' M-Fixpro')
                ->assertSee('Design &amp; Developed by plexCode', false);
        }
    }

    public function test_page_without_permission_shows_access_restricted_inside_the_shell(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->get('/salary')
            ->assertForbidden()
            ->assertSee('Access Restricted')
            ->assertSee('You don&#039;t have permission to view the Salary page.', false)
            ->assertSee('Sign out');
    }

    public function test_access_restricted_uses_the_module_name_of_the_page(): void
    {
        $this->actingAs(User::factory()->create(['permissions' => ['stockTransfer.view' => false]]))
            ->get('/stock-transfer')
            ->assertForbidden()
            ->assertSee('You don&#039;t have permission to view the Stock Transfer page.', false);
    }

    public function test_json_requests_get_a_plain_403(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'Staff']))
            ->getJson('/salary')
            ->assertForbidden()
            ->assertJsonMissing(['Access Restricted']);
    }

    public function test_pages_require_authentication(): void
    {
        $this->get('/products')->assertRedirect('/auth/login');
    }

    public function test_inactive_users_are_logged_out(): void
    {
        $this->actingAs(User::factory()->inactive()->create())
            ->get('/products')
            ->assertRedirect('/auth/login');

        $this->assertGuest();
    }

    public function test_sidebar_hides_links_without_permission_and_empty_groups(): void
    {
        $user = User::factory()->create([
            'role' => 'Staff',
            'permissions' => ['finance.view' => false, 'customers.view' => false, 'suppliers.view' => false],
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('products.index'))
            ->assertDontSee(route('finance.index'))
            ->assertDontSee(route('salary.index'))
            ->assertDontSee(route('customers.index'))
            ->assertDontSee('Contacts')
            ->assertDontSee('Finance');
    }

    public function test_user_without_any_permission_still_sees_settings(): void
    {
        $tree = Navigation::forUser(User::factory()->make(['role' => 'Unknown']), 'settings.index');

        $this->assertSame([[
            'label' => 'System',
            'icon' => 'settings',
            'active' => true,
            'children' => [[
                'label' => 'Settings',
                'icon' => 'settings',
                'route' => 'settings.index',
                'url' => route('settings.index'),
                'active' => true,
            ]],
        ]], $tree);
    }

    public function test_current_page_marks_its_link_and_group_active(): void
    {
        $tree = collect(Navigation::forUser(User::factory()->make(['role' => 'Admin']), 'grn.create'))->keyBy('label');

        $this->assertTrue($tree['Inventory']['active']);
        $this->assertFalse($tree['Sales']['active']);
        $this->assertFalse($tree['Dashboard']['active']);
        $this->assertSame(['GRN'], collect($tree['Inventory']['children'])->where('active', true)->pluck('label')->all());
    }

    public function test_active_link_is_highlighted_in_the_sidebar(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/grn')
            ->assertSeeInOrder(['href="'.route('grn.index').'"', 'aria-current="page"', 'bg-brand text-white font-medium'], false);
    }

    public function test_user_initials_use_first_and_last_name_or_email(): void
    {
        $this->assertSame('KP', User::factory()->make(['name' => 'kasun dilshan perera'])->initials());
        $this->assertSame('M', User::factory()->make(['name' => 'Madonna'])->initials());
        $this->assertSame('A', User::factory()->make(['name' => '', 'email' => 'admin@example.com'])->initials());
    }

    public function test_pagination_shows_range_and_page_links(): void
    {
        $paginator = new LengthAwarePaginator(range(1, Pagination::PER_PAGE), 25, Pagination::PER_PAGE, 2, ['path' => '/bills']);

        $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $paginator])
            ->assertSee('Showing 11–20 of 25')
            ->assertSeeInOrder(['Prev', '1', '2', '3', 'Next'])
            ->assertSee('/bills?page=3', false);
    }

    public function test_pagination_renders_nothing_for_an_empty_list(): void
    {
        $paginator = new LengthAwarePaginator([], 0, Pagination::PER_PAGE, 1);

        $this->blade('<x-pagination :paginator="$paginator" />', ['paginator' => $paginator])
            ->assertDontSee('Showing');
    }

    public function test_page_header_renders_title_subtitle_and_actions(): void
    {
        $this->blade('<x-page-header title="Jobs" subtitle="All repair jobs"><x-slot:actions><button>New Job</button></x-slot:actions></x-page-header>')
            ->assertSeeInOrder(['Jobs', 'All repair jobs', 'New Job']);
    }

    public function test_date_range_defaults_to_last_30_days(): void
    {
        $this->blade('<x-date-range />')
            ->assertSee('value="'.today()->subDays(30)->toDateString().'"', false)
            ->assertSee('value="'.today()->toDateString().'"', false);
    }

    public function test_shared_components_render(): void
    {
        $this->blade('<x-badge variant="success" dot>Paid</x-badge>')->assertSee('badge badge-success', false)->assertSee('Paid');
        $this->blade('<x-search-input placeholder="Search invoice no. or customer…" />')->assertSee('nexora-input pl-9', false);
        $this->blade('<x-modal name="open-shift" title="Open Shift">Body</x-modal>')->assertSee('Open Shift')->assertSee('max-w-md', false);
        $this->blade('<x-confirm-dialog name="delete" title="Delete product?" message="This cannot be undone." action="/products/1" />')
            ->assertSee('Delete product?')
            ->assertSee('name="_method" value="DELETE"', false);
        $this->blade('<x-searchable-select name="supplier_id" :options="[1 => \'Acme\']" empty-label="No supplier" />')
            ->assertSee('name="supplier_id"', false)
            ->assertSee('Acme');
    }
}
