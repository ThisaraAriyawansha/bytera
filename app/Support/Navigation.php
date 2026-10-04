<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    /**
     * The sidebar tree (SPEC §3). A `null` permission means the link is always visible.
     *
     * @var list<array{label: string, icon: string, route?: string, permission?: string|null, children?: list<array{label: string, icon: string, route: string, permission: string|null}>}>
     */
    public const ITEMS = [
        ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard', 'permission' => 'dashboard.view'],
        ['label' => 'POS / New Sale', 'icon' => 'shopping-cart', 'route' => 'sales.index', 'permission' => 'sales.view'],
        ['label' => 'Jobs', 'icon' => 'wrench', 'route' => 'jobs.index', 'permission' => 'jobs.view'],
        ['label' => 'Services', 'icon' => 'hammer', 'route' => 'services.index', 'permission' => 'services.view'],
        ['label' => 'Sales', 'icon' => 'receipt', 'children' => [
            ['label' => 'Bills', 'icon' => 'receipt', 'route' => 'bills.index', 'permission' => 'bills.view'],
            ['label' => 'Quotations', 'icon' => 'file-text', 'route' => 'quotations.index', 'permission' => 'quotations.view'],
            ['label' => 'Warranty', 'icon' => 'shield', 'route' => 'warranty.index', 'permission' => 'warranty.view'],
        ]],
        ['label' => 'Inventory', 'icon' => 'boxes', 'children' => [
            ['label' => 'Products', 'icon' => 'package', 'route' => 'products.index', 'permission' => 'products.view'],
            ['label' => 'GRN', 'icon' => 'package-plus', 'route' => 'grn.index', 'permission' => 'grn.view'],
            ['label' => 'Stock Transfer', 'icon' => 'arrow-left-right', 'route' => 'stock-transfer.index', 'permission' => 'stockTransfer.view'],
            ['label' => 'Stock Out', 'icon' => 'package-minus', 'route' => 'stock-out.index', 'permission' => 'stockOut.view'],
            ['label' => 'Stock Movements', 'icon' => 'history', 'route' => 'stock-movements.index', 'permission' => 'stockMovements.view'],
            ['label' => 'Brands', 'icon' => 'book-marked', 'route' => 'brands.index', 'permission' => 'brands.view'],
            ['label' => 'Categories', 'icon' => 'layers', 'route' => 'categories.index', 'permission' => 'categories.view'],
        ]],
        ['label' => 'Contacts', 'icon' => 'users', 'children' => [
            ['label' => 'Customers', 'icon' => 'users', 'route' => 'customers.index', 'permission' => 'customers.view'],
            ['label' => 'Suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'permission' => 'suppliers.view'],
        ]],
        ['label' => 'Finance', 'icon' => 'wallet', 'children' => [
            ['label' => 'Overview', 'icon' => 'wallet', 'route' => 'finance.index', 'permission' => 'finance.view'],
            ['label' => 'Salary', 'icon' => 'banknote', 'route' => 'salary.index', 'permission' => 'salary.view'],
        ]],
        ['label' => 'System', 'icon' => 'settings', 'children' => [
            ['label' => 'Audit Log', 'icon' => 'shield-check', 'route' => 'audit-log.index', 'permission' => 'auditLog.view'],
            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'settings.index', 'permission' => null],
        ]],
    ];

    /**
     * Build the sidebar for a user: links they can't access are dropped, empty groups are
     * dropped, and links / groups containing the current route are flagged as active.
     *
     * @return list<array{label: string, icon: string, route?: string, url?: string, active: bool, children?: list<array{label: string, icon: string, route: string, url: string, active: bool}>}>
     */
    public static function forUser(User $user, ?string $currentRoute): array
    {
        $tree = [];

        foreach (self::ITEMS as $item) {
            if (! isset($item['children'])) {
                if (self::userCanSee($user, $item)) {
                    $tree[] = self::resolveLink($item, $currentRoute);
                }

                continue;
            }

            $children = array_map(
                fn (array $child): array => self::resolveLink($child, $currentRoute),
                array_values(array_filter($item['children'], fn (array $child): bool => self::userCanSee($user, $child))),
            );

            if ($children === []) {
                continue;
            }

            $tree[] = [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'active' => in_array(true, array_column($children, 'active'), true),
                'children' => $children,
            ];
        }

        return $tree;
    }

    /**
     * Get every link in the tree, flattened.
     *
     * @return list<array{label: string, icon: string, route: string, permission: string|null}>
     */
    public static function links(): array
    {
        $links = [];

        foreach (self::ITEMS as $item) {
            array_push($links, ...($item['children'] ?? [$item]));
        }

        return $links;
    }

    /**
     * Get the module name shown on the Access Restricted screen for a route, e.g. "GRN".
     */
    public static function moduleForRoute(?string $routeName): ?string
    {
        foreach (self::links() as $link) {
            if ($link['permission'] !== null && self::matchesRoute($link['route'], $routeName)) {
                return Permissions::PERMISSION_CATALOG[$link['permission']]['module'];
            }
        }

        return null;
    }

    /**
     * Determine whether a link's route (or, for `*.index` routes, any sibling route such as
     * `grn.create`) is the current route.
     */
    private static function matchesRoute(string $linkRoute, ?string $currentRoute): bool
    {
        if ($currentRoute === null) {
            return false;
        }

        if ($currentRoute === $linkRoute) {
            return true;
        }

        return str_ends_with($linkRoute, '.index')
            && str_starts_with($currentRoute, substr($linkRoute, 0, -strlen('index')));
    }

    /**
     * @param  array{permission?: string|null}  $link
     */
    private static function userCanSee(User $user, array $link): bool
    {
        return $link['permission'] === null || $user->can($link['permission']);
    }

    /**
     * @param  array{label: string, icon: string, route: string, permission: string|null}  $link
     * @return array{label: string, icon: string, route: string, url: string, active: bool}
     */
    private static function resolveLink(array $link, ?string $currentRoute): array
    {
        return [
            'label' => $link['label'],
            'icon' => $link['icon'],
            'route' => $link['route'],
            'url' => route($link['route']),
            'active' => self::matchesRoute($link['route'], $currentRoute),
        ];
    }
}
