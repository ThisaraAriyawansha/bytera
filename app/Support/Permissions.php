<?php

namespace App\Support;

use App\Models\User;

class Permissions
{
    public const SUPER_ADMIN = 'Super Admin';

    public const ADMIN = 'Admin';

    public const MANAGER = 'Manager';

    public const CASHIER = 'Cashier';

    public const TECHNICIAN = 'Technician';

    public const STAFF = 'Staff';

    /**
     * Every permission key with its human label and the module it is grouped under.
     *
     * @var array<string, array{label: string, module: string}>
     */
    public const PERMISSION_CATALOG = [
        'dashboard.view' => ['label' => 'View Dashboard', 'module' => 'Dashboard'],

        'sales.view' => ['label' => 'View Sales', 'module' => 'Sales'],

        'grn.view' => ['label' => 'View GRN', 'module' => 'GRN'],
        'grn.create' => ['label' => 'Create GRN', 'module' => 'GRN'],
        'grn.edit' => ['label' => 'Edit GRN', 'module' => 'GRN'],

        'stockTransfer.view' => ['label' => 'View Stock Transfer', 'module' => 'Stock Transfer'],
        'stockTransfer.create' => ['label' => 'Create stock transfer', 'module' => 'Stock Transfer'],
        'stockTransfer.edit' => ['label' => 'Edit stock transfer', 'module' => 'Stock Transfer'],

        'stockOut.view' => ['label' => 'View Stock Out', 'module' => 'Stock Out'],
        'stockOut.create' => ['label' => 'Create stock out', 'module' => 'Stock Out'],
        'stockOut.edit' => ['label' => 'Edit stock out', 'module' => 'Stock Out'],

        'stockMovements.view' => ['label' => 'View Stock Movements', 'module' => 'Stock Movements'],

        'products.view' => ['label' => 'View Products', 'module' => 'Products'],
        'products.edit' => ['label' => 'Edit product', 'module' => 'Products'],
        'products.delete' => ['label' => 'Delete product', 'module' => 'Products'],
        'products.batch.edit' => ['label' => 'Edit batch cost/price/qty', 'module' => 'Products'],
        'products.unit.delete' => ['label' => 'Delete serial/unit', 'module' => 'Products'],

        'suppliers.view' => ['label' => 'View Suppliers', 'module' => 'Suppliers'],
        'suppliers.editContact' => ['label' => 'Edit supplier contact info', 'module' => 'Suppliers'],
        'suppliers.recordPayment' => ['label' => 'Record supplier payment', 'module' => 'Suppliers'],
        'suppliers.editPayment' => ['label' => 'Edit a recorded payment', 'module' => 'Suppliers'],
        'suppliers.sendStatement' => ['label' => 'Send supplier statement email', 'module' => 'Suppliers'],

        'bills.view' => ['label' => 'View Bills', 'module' => 'Bills'],
        'bills.cancel' => ['label' => 'Reverse / cancel a sale', 'module' => 'Bills'],
        'bills.edit' => ['label' => 'Edit bill details', 'module' => 'Bills'],

        'finance.view' => ['label' => 'View Finance', 'module' => 'Finance'],
        'finance.reviewShift' => ['label' => 'Review a closed shift', 'module' => 'Finance'],
        'finance.addExpense' => ['label' => 'Add an expense', 'module' => 'Finance'],
        'finance.deleteExpense' => ['label' => 'Delete an expense', 'module' => 'Finance'],

        'salary.view' => ['label' => 'View Salary', 'module' => 'Salary'],
        'salary.manageConfig' => ['label' => 'Edit employee salary setup', 'module' => 'Salary'],
        'salary.issue' => ['label' => 'Issue a salary payment', 'module' => 'Salary'],
        'salary.delete' => ['label' => 'Delete a salary payment', 'module' => 'Salary'],

        'auditLog.view' => ['label' => 'View Audit Log', 'module' => 'Audit Log'],

        'jobs.view' => ['label' => 'View Jobs', 'module' => 'Jobs'],
        'jobs.edit' => ['label' => 'Edit job details', 'module' => 'Jobs'],

        'quotations.view' => ['label' => 'View Quotations', 'module' => 'Quotations'],
        'quotations.delete' => ['label' => 'Delete quotation', 'module' => 'Quotations'],

        'brands.view' => ['label' => 'View Brands', 'module' => 'Brands'],
        'brands.delete' => ['label' => 'Delete brand', 'module' => 'Brands'],

        'categories.view' => ['label' => 'View Categories', 'module' => 'Categories'],
        'categories.delete' => ['label' => 'Delete category (main + sub)', 'module' => 'Categories'],

        'services.view' => ['label' => 'View Services', 'module' => 'Services'],
        'services.edit' => ['label' => 'Create / edit service', 'module' => 'Services'],
        'services.delete' => ['label' => 'Delete service', 'module' => 'Services'],

        'customers.view' => ['label' => 'View Customers', 'module' => 'Customers'],

        'warranty.view' => ['label' => 'View Warranty', 'module' => 'Warranty'],
    ];

    /**
     * Roles whose permissions can be configured (Super Admin is never configurable).
     *
     * @var list<string>
     */
    public const EDITABLE_ROLES = [
        self::ADMIN,
        self::MANAGER,
        self::CASHIER,
        self::TECHNICIAN,
        self::STAFF,
    ];

    /**
     * Roles an Admin is allowed to manage.
     *
     * @var list<string>
     */
    public const ADMIN_MANAGEABLE_ROLES = [
        self::MANAGER,
        self::CASHIER,
        self::TECHNICIAN,
        self::STAFF,
    ];

    /**
     * Extra keys a Manager receives on top of the shared defaults.
     *
     * @var list<string>
     */
    public const MANAGER_EXTRA_KEYS = [
        'grn.create',
        'stockTransfer.create',
        'stockOut.create',
        'finance.view',
        'finance.reviewShift',
        'finance.addExpense',
    ];

    /**
     * Get every permission key.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::PERMISSION_CATALOG);
    }

    /**
     * Get the keys granted to every editable role: all `.view` keys except
     * `salary.view`, plus `products.edit` and `suppliers.editContact`.
     *
     * @return list<string>
     */
    public static function defaultTrueKeys(): array
    {
        $viewKeys = array_filter(
            self::keys(),
            fn (string $key): bool => str_ends_with($key, '.view') && $key !== 'salary.view',
        );

        return [...array_values($viewKeys), 'products.edit', 'suppliers.editContact'];
    }

    /**
     * Get the full default permission map for a role. Unknown roles fail closed.
     *
     * @return array<string, bool>
     */
    public static function defaults(?string $role): array
    {
        $granted = match ($role) {
            self::SUPER_ADMIN, self::ADMIN => self::keys(),
            self::MANAGER => [...self::defaultTrueKeys(), ...self::MANAGER_EXTRA_KEYS],
            self::CASHIER, self::TECHNICIAN, self::STAFF => self::defaultTrueKeys(),
            default => [],
        };

        $map = array_fill_keys(self::keys(), false);

        foreach ($granted as $key) {
            $map[$key] = true;
        }

        return $map;
    }

    /**
     * Determine whether the user holds the given permission.
     */
    public static function can(User $user, string $key): bool
    {
        if ($user->role === self::SUPER_ADMIN) {
            return true;
        }

        if (is_array($user->permissions) && array_key_exists($key, $user->permissions)) {
            return (bool) $user->permissions[$key];
        }

        return self::defaults($user->role)[$key] ?? false;
    }

    /**
     * Determine whether a viewer with the given role may manage the permissions of a target role.
     */
    public static function canManagePermissionsFor(?string $viewerRole, ?string $targetRole): bool
    {
        return in_array($targetRole, self::assignableRoles($viewerRole), true);
    }

    /**
     * Determine whether the user may change shop info, low stock emails and the team (SPEC §4.4).
     */
    public static function isAdmin(User $user): bool
    {
        return in_array($user->role, [self::SUPER_ADMIN, self::ADMIN], true);
    }

    /**
     * Determine whether the viewer may edit or delete the target user. Nobody manages themselves.
     */
    public static function canManageUser(User $viewer, User $target): bool
    {
        return ! $viewer->is($target) && self::canManagePermissionsFor($viewer->role, $target->role);
    }

    /**
     * Get the roles a viewer may give to a new or edited user.
     *
     * @return list<string>
     */
    public static function assignableRoles(?string $viewerRole): array
    {
        return match ($viewerRole) {
            self::SUPER_ADMIN => self::EDITABLE_ROLES,
            self::ADMIN => self::ADMIN_MANAGEABLE_ROLES,
            default => [],
        };
    }

    /**
     * Get the catalog grouped by module, in catalog order, for the permission toggles.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedByModule(): array
    {
        $groups = [];

        foreach (self::PERMISSION_CATALOG as $key => $entry) {
            $groups[$entry['module']][$key] = $entry['label'];
        }

        return $groups;
    }

    /**
     * Get the user's effective permission map (what `can()` answers for every key).
     *
     * @return array<string, bool>
     */
    public static function effective(User $user): array
    {
        return array_combine(
            self::keys(),
            array_map(fn (string $key): bool => self::can($user, $key), self::keys()),
        );
    }

    /**
     * Build a full permission map from the list of granted keys. Unknown keys are ignored.
     *
     * @param  list<string>  $grantedKeys
     * @return array<string, bool>
     */
    public static function mapFromGranted(array $grantedKeys): array
    {
        return array_combine(
            self::keys(),
            array_map(fn (string $key): bool => in_array($key, $grantedKeys, true), self::keys()),
        );
    }
}
