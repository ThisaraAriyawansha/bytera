<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /**
     * Finance → Overview (SPEC §8.19) for a date range, cancelled sales excluded everywhere:
     * Revenue = Σ sale totals; COGS = Σ item cost × qty; Gross = Revenue − COGS; Net = Gross − Expenses;
     * Margin = Gross / Revenue. Plus the split-aware payment method breakdown, cashier performance and the
     * suppliers still owed money.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return array{
     *     revenue: float, cogs: float, gross_profit: float, expenses: float, net_profit: float, margin: ?float, sales_count: int,
     *     payment_methods: list<array{method: string, label: string, amount: float, percent: float}>,
     *     cashiers: list<array{cashier_id: int, cashier_name: string, sales_count: int, revenue: float}>,
     *     payables: list<array{id: int, name: string, balance: float}>, payables_total: float
     * }
     */
    public function overview(array $range): array
    {
        $totals = $this->totals($range);
        $payables = Supplier::query()->owing()->orderByDesc('balance')->orderBy('name')->get(['id', 'name', 'balance']);

        return [
            ...$totals,
            'payment_methods' => $this->paymentMethods($range),
            'cashiers' => $this->sales($range)
                ->selectRaw('cashier_id, max(cashier_name) as cashier_name, count(*) as sales_count, sum(total_amount) as revenue')
                ->groupBy('cashier_id')
                ->orderByDesc('revenue')
                ->toBase()
                ->get()
                ->map(fn (object $row): array => [
                    'cashier_id' => (int) $row->cashier_id,
                    'cashier_name' => (string) $row->cashier_name,
                    'sales_count' => (int) $row->sales_count,
                    'revenue' => SupplierService::cents($row->revenue) / 100,
                ])
                ->all(),
            'payables' => $payables->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'balance' => (float) $supplier->balance,
            ])->all(),
            'payables_total' => $payables->sum(fn (Supplier $supplier): int => SupplierService::cents($supplier->balance)) / 100,
        ];
    }

    /**
     * The profit figures for a date range, cancelled sales excluded. Shared by the Finance overview and the
     * Dashboard so both always show the same numbers for the same period.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return array{revenue: float, cogs: float, gross_profit: float, expenses: float, net_profit: float, margin: ?float, sales_count: int}
     */
    public function totals(array $range): array
    {
        $revenueCents = SupplierService::cents($this->sales($range)->sum('total_amount'));
        $cogsCents = SupplierService::cents(DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.status')
            ->whereBetween('sales.created_at', [$range['from'], $range['to']])
            ->sum(DB::raw('sale_items.cost_price * sale_items.qty')));
        $expensesCents = SupplierService::cents($this->expenses($range)->sum('amount'));
        $grossCents = $revenueCents - $cogsCents;

        return [
            'revenue' => $revenueCents / 100,
            'cogs' => $cogsCents / 100,
            'gross_profit' => $grossCents / 100,
            'expenses' => $expensesCents / 100,
            'net_profit' => ($grossCents - $expensesCents) / 100,
            'margin' => $revenueCents === 0 ? null : round($grossCents / $revenueCents * 100, 1),
            'sales_count' => $this->sales($range)->count(),
        ];
    }

    /**
     * Finance → Daily Balance (cash book): every day of the range, oldest first, with its income (sales),
     * expenses and net. The opening balance and running closing balance are applied on top by the caller.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return list<array{date: string, income: float, expenses: float, net: float}>
     */
    public function dailyBalance(array $range): array
    {
        $income = $this->sales($range)
            ->selectRaw('date(created_at) as day, sum(total_amount) as total')
            ->groupByRaw('date(created_at)')
            ->toBase()
            ->pluck('total', 'day');
        $expenses = $this->expenses($range)
            ->selectRaw('date(created_at) as day, sum(amount) as total')
            ->groupByRaw('date(created_at)')
            ->toBase()
            ->pluck('total', 'day');

        $days = [];

        foreach (CarbonPeriod::create($range['from']->startOfDay(), '1 day', $range['to']->startOfDay()) as $day) {
            $date = $day->toDateString();
            $incomeCents = SupplierService::cents($income[$date] ?? 0);
            $expensesCents = SupplierService::cents($expenses[$date] ?? 0);

            $days[] = [
                'date' => $date,
                'income' => $incomeCents / 100,
                'expenses' => $expensesCents / 100,
                'net' => ($incomeCents - $expensesCents) / 100,
            ];
        }

        return $days;
    }

    /**
     * Add the running closing balance to the cash book rows, starting from the opening balance.
     *
     * @param  list<array{date: string, income: float, expenses: float, net: float}>  $days
     * @return list<array{date: string, income: float, expenses: float, net: float, closing: float}>
     */
    public static function withClosingBalances(array $days, float|int|string $openingBalance): array
    {
        $runningCents = SupplierService::cents($openingBalance);

        return array_map(function (array $day) use (&$runningCents): array {
            $runningCents += SupplierService::cents($day['net']);

            return [...$day, 'closing' => $runningCents / 100];
        }, $days);
    }

    /**
     * The payment method breakdown, split-aware: a split sale adds each leg to its own method.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return list<array{method: string, label: string, amount: float, percent: float}>
     */
    private function paymentMethods(array $range): array
    {
        $cents = array_fill_keys(array_keys(Sale::PAYMENT_METHODS), 0);

        $single = $this->sales($range)
            ->whereNull('payments')
            ->selectRaw('payment_method, sum(total_amount) as total')
            ->groupBy('payment_method')
            ->toBase()
            ->pluck('total', 'payment_method');

        foreach ($single as $method => $total) {
            $cents[$method] = ($cents[$method] ?? 0) + SupplierService::cents($total);
        }

        foreach ($this->sales($range)->whereNotNull('payments')->select(['id', 'payment_method', 'payments', 'total_amount'])->lazyById(500) as $sale) {
            foreach ($sale->paymentLegs() as $leg) {
                $cents[$leg['method']] = ($cents[$leg['method']] ?? 0) + SupplierService::cents($leg['amount']);
            }
        }

        $totalCents = array_sum($cents);

        return array_map(fn (string $method): array => [
            'method' => $method,
            'label' => Sale::PAYMENT_METHODS[$method] ?? $method,
            'amount' => $cents[$method] / 100,
            'percent' => $totalCents === 0 ? 0.0 : round($cents[$method] / $totalCents * 100, 1),
        ], array_keys($cents));
    }

    /**
     * Non-cancelled sales in the range.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Builder<Sale>
     */
    private function sales(array $range): Builder
    {
        return Sale::query()->whereNull('status')->whereBetween('created_at', [$range['from'], $range['to']]);
    }

    /**
     * Expenses in the range.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Builder<Expense>
     */
    private function expenses(array $range): Builder
    {
        return Expense::query()->whereBetween('created_at', [$range['from'], $range['to']]);
    }
}
