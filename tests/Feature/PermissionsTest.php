<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const DEFAULT_TRUE = [
        'dashboard.view',
        'sales.view',
        'grn.view',
        'stockTransfer.view',
        'stockOut.view',
        'stockMovements.view',
        'products.view',
        'suppliers.view',
        'bills.view',
        'finance.view',
        'auditLog.view',
        'jobs.view',
        'quotations.view',
        'brands.view',
        'categories.view',
        'services.view',
        'customers.view',
        'warranty.view',
        'products.edit',
        'suppliers.editContact',
    ];

    public function test_catalog_contains_every_spec_key_with_label_and_module(): void
    {
        $this->assertCount(47, Permissions::PERMISSION_CATALOG);

        foreach (Permissions::PERMISSION_CATALOG as $key => $entry) {
            $this->assertNotEmpty($entry['label'], $key);
            $this->assertNotEmpty($entry['module'], $key);
        }

        $this->assertSame(['label' => 'Create GRN', 'module' => 'GRN'], Permissions::PERMISSION_CATALOG['grn.create']);
        $this->assertSame('Edit batch cost/price/qty', Permissions::PERMISSION_CATALOG['products.batch.edit']['label']);
    }

    public function test_defaults_always_return_a_full_map(): void
    {
        foreach ([...Permissions::EDITABLE_ROLES, 'Unknown', null] as $role) {
            $this->assertSame(Permissions::keys(), array_keys(Permissions::defaults($role)));
        }
    }

    public function test_admin_defaults_grant_every_key(): void
    {
        $this->assertSame(Permissions::keys(), $this->grantedKeys('Admin'));
    }

    public function test_manager_defaults_add_stock_and_finance_keys(): void
    {
        $expected = [
            ...self::DEFAULT_TRUE,
            'grn.create',
            'stockTransfer.create',
            'stockOut.create',
            'finance.reviewShift',
            'finance.addExpense',
        ];

        $this->assertEqualsCanonicalizing($expected, $this->grantedKeys('Manager'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function basicRoles(): array
    {
        return [
            'cashier' => ['Cashier'],
            'technician' => ['Technician'],
            'staff' => ['Staff'],
        ];
    }

    #[DataProvider('basicRoles')]
    public function test_basic_roles_get_default_true_keys_only(string $role): void
    {
        $this->assertEqualsCanonicalizing(self::DEFAULT_TRUE, $this->grantedKeys($role));
    }

    public function test_salary_view_is_not_a_default_for_non_admins(): void
    {
        foreach (['Manager', 'Cashier', 'Technician', 'Staff'] as $role) {
            $this->assertFalse(Permissions::defaults($role)['salary.view'], $role);
        }
    }

    public function test_unknown_or_empty_role_fails_closed(): void
    {
        foreach (['Unknown', '', null] as $role) {
            $this->assertSame([], $this->grantedKeys($role));
        }
    }

    public function test_can_prefers_stored_permissions_over_defaults(): void
    {
        $user = User::factory()->make([
            'role' => 'Staff',
            'permissions' => ['products.view' => false, 'salary.view' => true],
        ]);

        $this->assertFalse(Permissions::can($user, 'products.view'));
        $this->assertTrue(Permissions::can($user, 'salary.view'));
        $this->assertTrue(Permissions::can($user, 'customers.view'));
        $this->assertFalse(Permissions::can($user, 'grn.create'));
        $this->assertFalse(Permissions::can($user, 'not.a.key'));
    }

    public function test_super_admin_can_do_everything_regardless_of_stored_permissions(): void
    {
        $user = User::factory()->superAdmin()->make(['permissions' => ['dashboard.view' => false]]);

        $this->assertTrue(Permissions::can($user, 'dashboard.view'));
        $this->assertTrue(Gate::forUser($user)->allows('dashboard.view'));
        $this->assertTrue(Gate::forUser($user)->allows('salary.delete'));
    }

    public function test_gates_are_defined_for_every_key(): void
    {
        $user = User::factory()->make(['role' => 'Cashier', 'permissions' => ['bills.cancel' => true]]);

        foreach (Permissions::keys() as $key) {
            $this->assertTrue(Gate::has($key), $key);
        }

        $this->assertTrue(Gate::forUser($user)->allows('bills.view'));
        $this->assertTrue(Gate::forUser($user)->allows('bills.cancel'));
        $this->assertFalse(Gate::forUser($user)->allows('salary.view'));
    }

    public function test_super_admin_can_manage_every_editable_role(): void
    {
        foreach (['Admin', 'Manager', 'Cashier', 'Technician', 'Staff'] as $target) {
            $this->assertTrue(Permissions::canManagePermissionsFor('Super Admin', $target), $target);
        }

        $this->assertFalse(Permissions::canManagePermissionsFor('Super Admin', 'Super Admin'));
        $this->assertFalse(Permissions::canManagePermissionsFor('Super Admin', 'Unknown'));
    }

    public function test_admin_can_manage_only_lower_roles(): void
    {
        foreach (['Manager', 'Cashier', 'Technician', 'Staff'] as $target) {
            $this->assertTrue(Permissions::canManagePermissionsFor('Admin', $target), $target);
        }

        $this->assertFalse(Permissions::canManagePermissionsFor('Admin', 'Admin'));
        $this->assertFalse(Permissions::canManagePermissionsFor('Admin', 'Super Admin'));
    }

    public function test_other_roles_cannot_manage_permissions(): void
    {
        foreach (['Manager', 'Cashier', 'Technician', 'Staff', 'Unknown', null] as $viewer) {
            foreach (Permissions::EDITABLE_ROLES as $target) {
                $this->assertFalse(Permissions::canManagePermissionsFor($viewer, $target), "{$viewer} -> {$target}");
            }
        }
    }

    /**
     * @return list<string>
     */
    private function grantedKeys(?string $role): array
    {
        return array_keys(array_filter(Permissions::defaults($role)));
    }
}
