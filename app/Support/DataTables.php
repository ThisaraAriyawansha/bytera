<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DataTables
{
    /**
     * Application tables shown under Database Usage and offered for export, with their labels.
     * Credential tables (sessions, reset codes) are deliberately left out.
     *
     * @var array<string, string>
     */
    public const TABLES = [
        'users' => 'Users',
        'shop_settings' => 'Shop settings',
        'counters' => 'Counters',
        'brands' => 'Brands',
        'main_categories' => 'Main categories',
        'sub_categories' => 'Sub categories',
        'services' => 'Services',
        'products' => 'Products',
        'product_batches' => 'Product batches',
        'product_units' => 'Product units',
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'supplier_payments' => 'Supplier payments',
        'sales' => 'Sales',
        'sale_items' => 'Sale items',
        'warranties' => 'Warranties',
        'quotations' => 'Quotations',
        'quotation_items' => 'Quotation items',
        'jobs' => 'Jobs',
        'job_status_history' => 'Job status history',
        'stock_movements' => 'Stock movements',
        'grns' => 'GRNs',
        'grn_items' => 'GRN items',
        'stock_transfers' => 'Stock transfers',
        'stock_transfer_items' => 'Stock transfer items',
        'stock_outs' => 'Stock outs',
        'stock_out_items' => 'Stock out items',
        'shifts' => 'Shifts',
        'expenses' => 'Expenses',
        'salary_payments' => 'Salary payments',
        'audit_logs' => 'Audit logs',
    ];

    /**
     * Transactional tables that "Clean table" may empty.
     *
     * @var list<string>
     */
    public const CLEANABLE = [
        'supplier_payments',
        'sales',
        'sale_items',
        'warranties',
        'quotations',
        'quotation_items',
        'jobs',
        'job_status_history',
        'stock_movements',
        'grns',
        'grn_items',
        'stock_transfers',
        'stock_transfer_items',
        'stock_outs',
        'stock_out_items',
        'shifts',
        'expenses',
        'salary_payments',
        'audit_logs',
    ];

    /**
     * Columns never written to an export.
     *
     * @var array<string, list<string>>
     */
    public const HIDDEN_COLUMNS = [
        'users' => ['password', 'remember_token'],
    ];

    /**
     * Get the row count of every listed table.
     *
     * @return array<string, int>
     */
    public static function rowCounts(): array
    {
        $counts = [];

        foreach (array_keys(self::TABLES) as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * Strip columns that must never leave the database from an exported row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function exportableRow(string $table, array $row): array
    {
        return array_diff_key($row, array_flip(self::HIDDEN_COLUMNS[$table] ?? []));
    }
}
