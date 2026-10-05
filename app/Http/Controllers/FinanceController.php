<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shift;
use App\Services\FinanceService;
use App\Services\ShiftService;
use App\Support\DateRange;
use App\Support\Pagination;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    /**
     * The Finance tabs, in order (SPEC §8.19).
     *
     * @var array<string, string>
     */
    public const TABS = [
        'overview' => 'Overview',
        'daily' => 'Daily Balance',
        'shifts' => 'Shifts',
        'expenses' => 'Expenses',
    ];

    public function __construct(private FinanceService $finance) {}

    /**
     * The Finance page with the chosen tab's figures. Overview and Daily Balance default to the 1st of this
     * month → today; Shifts and Expenses to the last 30 days.
     */
    public function index(Request $request): View
    {
        $tab = $this->tab($request);
        $range = $this->range($request, $tab);
        $user = $request->user();

        $data = match ($tab) {
            'overview' => ['overview' => $this->finance->overview($range)],
            'daily' => ['days' => $this->finance->dailyBalance($range)],
            'shifts' => [
                'shifts' => $this->filteredShifts($request, $range)->paginate(Pagination::PER_PAGE)->withQueryString(),
                'cashiers' => Shift::query()->selectRaw('cashier_id, max(cashier_name) as cashier_name')->groupBy('cashier_id')->orderBy('cashier_name')->toBase()->get(),
                'filters' => $this->shiftFilters($request),
            ],
            'expenses' => [
                'expenses' => $this->filteredExpenses($request, $range)->paginate(Pagination::PER_PAGE)->withQueryString(),
                'expensesTotal' => (float) $this->filteredExpenses($request, $range)->reorder()->sum('amount'),
                'category' => $this->expenseCategory($request),
                'openShifts' => Shift::query()->where('status', 'open')->orderBy('opened_at')->get()
                    ->map(fn (Shift $shift): array => [
                        'id' => $shift->id,
                        'label' => "{$shift->shift_no} · {$shift->cashier_name}",
                        'expected_cash' => ShiftService::expectedCash($shift),
                    ])
                    ->all(),
            ],
        };

        return view('finance.index', [
            'tab' => $tab,
            'range' => $range,
            'canReviewShift' => $user->can('finance.reviewShift'),
            'canForceClose' => Permissions::isAdmin($user),
            'canAddExpense' => $user->can('finance.addExpense'),
            'canDeleteExpense' => $user->can('finance.deleteExpense'),
            ...$data,
        ]);
    }

    /**
     * Export CSV of the chosen tab with its current filters. The Daily Balance export takes the opening balance
     * the page holds (`opening`).
     */
    public function export(Request $request): StreamedResponse
    {
        $tab = $this->tab($request);
        $range = $this->range($request, $tab);
        $filename = "finance-{$tab}-{$range['from']->toDateString()}-to-{$range['to']->toDateString()}.csv";

        return response()->streamDownload(function () use ($request, $tab, $range): void {
            $output = fopen('php://output', 'w');

            match ($tab) {
                'overview' => $this->writeOverview($output, $range),
                'daily' => $this->writeDailyBalance($output, $range, (float) $request->query('opening', 0)),
                'shifts' => $this->writeShifts($output, $this->filteredShifts($request, $range)),
                'expenses' => $this->writeExpenses($output, $this->filteredExpenses($request, $range)),
            };

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  resource  $output
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     */
    private function writeOverview($output, array $range): void
    {
        $overview = $this->finance->overview($range);

        fputcsv($output, ['Finance overview', $range['from']->toDateString().' to '.$range['to']->toDateString()], escape: '');
        fputcsv($output, [], escape: '');
        fputcsv($output, ['Metric', 'Amount'], escape: '');

        foreach ([
            'Revenue' => $overview['revenue'],
            'COGS' => $overview['cogs'],
            'Gross Profit' => $overview['gross_profit'],
            'Expenses' => $overview['expenses'],
            'Net Profit' => $overview['net_profit'],
            'Margin %' => $overview['margin'],
            'Sales' => $overview['sales_count'],
        ] as $label => $value) {
            fputcsv($output, [$label, $value], escape: '');
        }

        fputcsv($output, [], escape: '');
        fputcsv($output, ['Payment Method', 'Amount', '%'], escape: '');

        foreach ($overview['payment_methods'] as $method) {
            fputcsv($output, [$method['label'], $method['amount'], $method['percent']], escape: '');
        }

        fputcsv($output, [], escape: '');
        fputcsv($output, ['Cashier', 'Sales', 'Revenue'], escape: '');

        foreach ($overview['cashiers'] as $cashier) {
            fputcsv($output, [$cashier['cashier_name'], $cashier['sales_count'], $cashier['revenue']], escape: '');
        }

        fputcsv($output, [], escape: '');
        fputcsv($output, ['Supplier', 'Balance'], escape: '');

        foreach ($overview['payables'] as $supplier) {
            fputcsv($output, [$supplier['name'], $supplier['balance']], escape: '');
        }

        fputcsv($output, ['Total', $overview['payables_total']], escape: '');
    }

    /**
     * @param  resource  $output
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     */
    private function writeDailyBalance($output, array $range, float $openingBalance): void
    {
        fputcsv($output, ['Date', 'Opening Balance', 'Income', 'Expenses', 'Net', 'Closing Balance'], escape: '');

        $opening = $openingBalance;

        foreach (FinanceService::withClosingBalances($this->finance->dailyBalance($range), $openingBalance) as $day) {
            fputcsv($output, [$day['date'], $opening, $day['income'], $day['expenses'], $day['net'], $day['closing']], escape: '');
            $opening = $day['closing'];
        }
    }

    /**
     * @param  resource  $output
     * @param  Builder<Shift>  $shifts
     */
    private function writeShifts($output, Builder $shifts): void
    {
        fputcsv($output, [
            'Shift No.', 'Cashier', 'Status', 'Opened', 'Closed', 'Float', 'Cash', 'Card', 'Transfer', 'KokoPay', 'Sales',
            'Cash Paid Out', 'Expected', 'Counted', 'Variance', 'Force Closed', 'Closed By', 'Close Note', 'Review', 'Reviewed By', 'Review Note',
        ], escape: '');

        foreach ($shifts->lazy() as $shift) {
            fputcsv($output, [
                $shift->shift_no,
                $shift->cashier_name,
                ucfirst($shift->status),
                $shift->opened_at->format('Y-m-d H:i'),
                $shift->closed_at?->format('Y-m-d H:i'),
                $shift->opening_float,
                $shift->cash_sales_total,
                $shift->card_sales_total,
                $shift->transfer_sales_total,
                $shift->kokopay_sales_total,
                $shift->sales_count,
                $shift->cash_expenses_total,
                $shift->expected_cash ?? ShiftService::expectedCash($shift),
                $shift->counted_cash,
                $shift->variance,
                $shift->force_closed ? 'Yes' : 'No',
                $shift->closed_by_name,
                $shift->close_note,
                Shift::REVIEW_STATUSES[$shift->review_status]['label'] ?? '',
                $shift->reviewed_by_name,
                $shift->review_note,
            ], escape: '');
        }
    }

    /**
     * @param  resource  $output
     * @param  Builder<Expense>  $expenses
     */
    private function writeExpenses($output, Builder $expenses): void
    {
        fputcsv($output, ['Expense No.', 'Category', 'Amount', 'Note', 'Paid By', 'Drawer', 'Date'], escape: '');

        foreach ($expenses->lazy() as $expense) {
            fputcsv($output, [
                $expense->expense_no,
                $expense->categoryLabel(),
                $expense->amount,
                $expense->note,
                $expense->paid_by_name,
                $expense->shift_no,
                $expense->created_at->format('Y-m-d H:i'),
            ], escape: '');
        }
    }

    /**
     * The Shifts filters from the query string.
     *
     * @return array{cashier_id: ?int, status: ?string, review: ?string, search: string}
     */
    private function shiftFilters(Request $request): array
    {
        $status = $request->query('status');
        $review = $request->query('review');
        $cashier = $request->query('cashier_id');

        return [
            'cashier_id' => is_string($cashier) && ctype_digit($cashier) ? (int) $cashier : null,
            'status' => in_array($status, ['open', 'closed'], true) ? $status : null,
            'review' => is_string($review) && array_key_exists($review, Shift::REVIEW_STATUSES) ? $review : null,
            'search' => trim((string) $request->query('search')),
        ];
    }

    /**
     * Shifts opened in the range, filtered by cashier, open / closed, review status and a cashier / shift no. search.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Builder<Shift>
     */
    private function filteredShifts(Request $request, array $range): Builder
    {
        $filters = $this->shiftFilters($request);
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['search']).'%';

        return Shift::query()
            ->whereBetween('opened_at', [$range['from'], $range['to']])
            ->when($filters['cashier_id'] !== null, fn (Builder $query) => $query->where('cashier_id', $filters['cashier_id']))
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['review'] !== null, fn (Builder $query) => $query->where('review_status', $filters['review']))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('shift_no', 'like', $pattern)
                ->orWhere('cashier_name', 'like', $pattern)))
            ->latest('opened_at')
            ->latest('id');
    }

    /**
     * Expenses in the range, optionally of one category.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Builder<Expense>
     */
    private function filteredExpenses(Request $request, array $range): Builder
    {
        $category = $this->expenseCategory($request);

        return Expense::query()
            ->with('linkedSalaryPayment:id,payment_no')
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->when($category !== null, fn (Builder $query) => $query->where('category', $category))
            ->latest()
            ->latest('id');
    }

    private function expenseCategory(Request $request): ?string
    {
        $category = $request->query('category');

        return is_string($category) && array_key_exists($category, Expense::CATEGORIES) ? $category : null;
    }

    private function tab(Request $request): string
    {
        $tab = $request->query('tab');

        return is_string($tab) && array_key_exists($tab, self::TABS) ? $tab : 'overview';
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    private function range(Request $request, string $tab): array
    {
        return DateRange::fromRequest(
            $request,
            defaultFrom: in_array($tab, ['overview', 'daily'], true) ? CarbonImmutable::today()->startOfMonth() : null,
        );
    }
}
