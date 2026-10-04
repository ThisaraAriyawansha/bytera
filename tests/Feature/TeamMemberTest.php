<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeamMemberTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_removed_permission_hides_the_menu_item_for_a_new_cashier(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->postJson(route('settings.users.store'), [
                'name' => 'Kasun Perera',
                'email' => 'Kasun@Example.com',
                'role' => 'Cashier',
                'password' => 'secret1',
                'password_confirmation' => 'secret1',
            ])
            ->assertCreated();

        $cashier = User::query()->where('email', 'kasun@example.com')->sole();
        $this->assertSame(Permissions::defaults('Cashier'), $cashier->permissions);
        $this->assertSame('active', $cashier->status);
        $this->assertTrue(Hash::check('secret1', $cashier->password));

        $granted = array_keys(array_filter(Permissions::defaults('Cashier')));

        $this->actingAs($admin)
            ->putJson(route('settings.users.update', $cashier), [
                'name' => 'Kasun Perera',
                'role' => 'Cashier',
                'status' => 'active',
                'permissions' => array_values(array_diff($granted, ['customers.view'])),
            ])
            ->assertOk();

        $this->assertFalse($cashier->fresh()->permissions['customers.view']);

        $this->actingAs($cashier->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('customers.index'))
            ->assertSee(route('suppliers.index'));

        $this->get(route('customers.index'))->assertForbidden()->assertSee('Access Restricted');
    }

    public function test_admin_can_only_create_lower_roles(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('settings.users.store'), $this->newUser(['role' => 'Admin']))
            ->assertJsonValidationErrors(['role' => 'You are not allowed to give this role.']);

        $this->actingAs($admin)
            ->postJson(route('settings.users.store'), $this->newUser(['role' => 'Super Admin']))
            ->assertJsonValidationErrors('role');

        $this->actingAs($admin)
            ->postJson(route('settings.users.store'), $this->newUser(['role' => 'Manager']))
            ->assertCreated();
    }

    public function test_super_admin_can_create_an_admin_with_every_permission(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->postJson(route('settings.users.store'), $this->newUser(['role' => 'Admin']))
            ->assertCreated();

        $this->assertSame(Permissions::defaults('Admin'), User::query()->where('role', 'Admin')->sole()->permissions);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'manager' => ['Manager'],
            'cashier' => ['Cashier'],
            'technician' => ['Technician'],
            'staff' => ['Staff'],
        ];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_cannot_manage_the_team(string $role): void
    {
        $viewer = User::factory()->create(['role' => $role]);
        $target = User::factory()->create(['role' => 'Staff']);

        $this->actingAs($viewer)->postJson(route('settings.users.store'), $this->newUser())->assertForbidden();
        $this->actingAs($viewer)->putJson(route('settings.users.update', $target), $this->update())->assertForbidden();
        $this->actingAs($viewer)->delete(route('settings.users.destroy', $target))->assertForbidden();

        $this->assertModelExists($target);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_admin_cannot_edit_or_delete_a_peer_admin_or_super_admin(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $target) {
            $this->actingAs($admin)->putJson(route('settings.users.update', $target), $this->update())->assertForbidden();
            $this->actingAs($admin)->delete(route('settings.users.destroy', $target))->assertForbidden();
            $this->assertModelExists($target);
        }
    }

    public function test_admin_cannot_promote_a_user_to_admin(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('settings.users.update', $staff), $this->update(['role' => 'Admin']))
            ->assertJsonValidationErrors('role');

        $this->assertSame('Staff', $staff->fresh()->role);
    }

    public function test_super_admin_can_restrict_an_admin_but_not_another_super_admin(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($superAdmin)
            ->putJson(route('settings.users.update', $admin), $this->update(['role' => 'Admin', 'permissions' => ['dashboard.view']]))
            ->assertOk();

        $admin->refresh();
        $this->assertTrue(Permissions::can($admin, 'dashboard.view'));
        $this->assertFalse(Permissions::can($admin, 'bills.view'));

        $this->actingAs($superAdmin)
            ->putJson(route('settings.users.update', User::factory()->superAdmin()->create()), $this->update())
            ->assertForbidden();
    }

    public function test_nobody_can_edit_or_delete_themselves(): void
    {
        foreach ([User::factory()->admin()->create(), User::factory()->superAdmin()->create()] as $viewer) {
            $this->actingAs($viewer)->putJson(route('settings.users.update', $viewer), $this->update())->assertForbidden();
            $this->actingAs($viewer)->delete(route('settings.users.destroy', $viewer))->assertForbidden();
            $this->assertModelExists($viewer);
        }
    }

    public function test_update_rejects_unknown_keys_and_stores_a_full_map(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('settings.users.update', $staff), $this->update(['permissions' => ['salary.view', 'not.a.key']]))
            ->assertJsonValidationErrors('permissions.1');

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('settings.users.update', $staff), $this->update([
                'name' => 'Renamed',
                'role' => 'Technician',
                'status' => 'inactive',
                'permissions' => ['salary.view'],
            ]))
            ->assertOk();

        $staff->refresh();
        $this->assertSame('Renamed', $staff->name);
        $this->assertSame('Technician', $staff->role);
        $this->assertSame('inactive', $staff->status);
        $this->assertSame(Permissions::keys(), array_keys($staff->permissions));
        $this->assertSame(['salary.view'], array_keys(array_filter($staff->permissions)));
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);

        $this->actingAs(User::factory()->admin()->create())
            ->putJson(route('settings.users.update', $staff), $this->update(['status' => 'inactive']))
            ->assertOk();

        $this->actingAs($staff->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_delete_a_lower_role_user(): void
    {
        $staff = User::factory()->create(['role' => 'Staff', 'name' => 'Nimal']);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('settings.users.destroy', $staff))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'Nimal has been deleted.');

        $this->assertModelMissing($staff);
    }

    public function test_user_with_records_is_not_deleted(): void
    {
        $cashier = User::factory()->create(['role' => 'Cashier', 'name' => 'Nimal']);
        AuditLog::query()->create([
            'collection_name' => 'sales',
            'doc_id' => '1',
            'label' => 'INV-00001',
            'changes' => [],
            'performed_by_id' => $cashier->id,
            'performed_by_name' => $cashier->name,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('settings.users.destroy', $cashier))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('error', "Nimal has records in the system and can't be deleted. Set them to Inactive instead.");

        $this->assertModelExists($cashier);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newUser(array $overrides = []): array
    {
        return [
            'name' => 'New User',
            'email' => fake()->unique()->safeEmail(),
            'role' => 'Staff',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function update(array $overrides = []): array
    {
        return [
            'name' => 'Updated',
            'role' => 'Staff',
            'status' => 'active',
            'permissions' => ['dashboard.view'],
            ...$overrides,
        ];
    }
}
