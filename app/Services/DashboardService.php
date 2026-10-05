<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStatusHistory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * The period toggle (SPEC §8.3): each period ends today and is compared with the same number of days before it.
     *
     * @var array<string, array{label: string, title: string, days: int, comparison: string}>
     */
    public const PERIODS = [
        'today' => ['label' => 'Today', 'title' => 'Today', 'days' => 1, 'comparison' => 'vs yesterday'],
        '7d' => ['label' => '7 Days', 'title' => 'Last 7 days', 'days' => 7, 'comparison' => 'vs previous 7 days'],
        '30d' => ['label' => '30 Days', 'title' => 'Last 30 days', 'days' => 30, 'comparison' => 'vs previous 30 days'],
    ];

    public const DEFAULT_PERIOD = 'today';

    /**
     * The Busiest Hours heatmap always looks at the last 30 days.
     */
    public const HEATMAP_DAYS = 30;

    /**
     * Repairs still being worked on.
     *
     * @var list<string>
     */
    public const OPEN_JOB_STATUSES = ['pending', 'ongoing'];

    /**
     * Payment statuses of a bill that still has money to collect.
     *
     * @var list<string>
     */
    public const UNSETTLED_PAYMENT_STATUSES = ['partial', 'pending'];

    /**
     * The repair pipeline stages, in workflow order, with their bar colour.
     *
     * @var array<string, array{label: string, color: string}>
     */
    public const PIPELINE = [
        'pending' => ['label' => 'Pending', 'color' => '#d4d4d8'],
        'ongoing' => ['label' => 'In repair', 'color' => '#0a0a0a'],
        'done' => ['label' => 'Ready', 'color' => '#e30613'],
        'delivered' => ['label' => 'Delivered', 'color' => '#16a34a'],
        'unrepairable' => ['label' => 'Unrepairable', 'color' => '#a1a1aa'],
    ];

    /**
     * Colours for the Sales Mix segments, largest category first.
     *
     * @var list<string>
     */
    public const MIX_COLORS = ['#e30613', '#0a0a0a', '#71717a', '#a1a1aa', '#d4d4d8', '#fca5a5'];

    /**
     * How many rows the short lists show.
     */
    private const LIST_LIMIT = 5;

    private const LOW_STOCK_LIMIT = 6;

    private const ACTIVITY_LIMIT = 8;

    public function __construct(private FinanceService $finance) {}

    /**
     * Resolve the period from the request value, falling back to Today.
     */
    public static function period(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::PERIODS) ? $value : self::DEFAULT_PERIOD;
    }

    /**
     * The current period (ending today) and the previous period of the same length right before it.
     *
     * @return array{current: array{from: CarbonImmutable, to: CarbonImmutable}, previous: array{from: CarbonImmutable, to: CarbonImmutable}}
     */
    public static function ranges(string $period, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $days = self::PERIODS[$period]['days'];
        $from = $today->subDays($days - 1)->startOfDay();

        return [
            'current' => ['from' => $from, 'to' => $today->endOfDay()],
            'previous' => ['from' => $from->subDays($days), 'to' => $from->subDay()->endOfDay()],
        ];
    }

    /**
     * Percentage change from the previous value: null when there was nothing to compare with.
     */
    public static function delta(float|int $current, float|int $previous): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    /**
     * Short relative time for the activity feed: "just now", "5m ago", "3h ago", "2d ago".
     */
    public static function ago(CarbonInterface $time, ?CarbonInterface $now = null): string
    {
        $seconds = max(0, (int) ($now ?? now())->diffInSeconds($time, true));

        return match (true) {
            $seconds < 60 => 'just now',
            $seconds < 3600 => intdiv($seconds, 60).'m ago',
            $seconds < 86400 => intdiv($seconds, 3600).'h ago',
            default => intdiv($seconds, 86400).'d ago',
        };
    }

    /**
     * Every Dashboard section for the user and period (SPEC §8.3). Cancelled sales are excluded everywhere, and
     * sections the user may not see are null and never queried. Revenue, cost of goods, expenses, payment
     * methods and the team come from the Finance figures for the same dates, so the two pages always agree.
     *
     * @return array<string, mixed>
     */
    public function build(User $user, string $period): array
    {
        $canViewFinance = $user->can('finance.view');
        $canViewJobs = $user->can('jobs.view');

        $ranges = self::ranges($period);
        $overview = $this->finance->overview($ranges['current']);
        $previous = $this->finance->totals($ranges['previous']);
        $counts = $this->counts();

        return [
            'period' => $period,
            'range' => $ranges['current'],
            'canViewFinance' => $canViewFinance,
            'canViewJobs' => $canViewJobs,
            'attention' => [
                'overdue_repairs' => $counts['overdue_repairs'],
                'ready_for_pickup' => $counts['ready_for_pickup'],
                'low_stock' => $counts['low_stock'],
                'unsettled_bills' => $canViewFinance ? $counts['unsettled_bills'] : 0,
            ],
            'revenue' => $canViewFinance ? $this->revenue($ranges, $period, $overview, $previous) : null,
            'profit' => $canViewFinance ? $this->profitAndLoss($overview, $previous) : null,
            'kpis' => $this->kpis($ranges, $counts, $overview, $previous, $canViewJobs && $canViewFinance),
            'workshop' => $canViewJobs ? $this->workshop($ranges['current'], $counts) : null,
            'heatmap' => $this->busiestHours(),
            'topProducts' => $canViewFinance ? $this->topProducts($ranges['current']) : null,
            'salesMix' => $this->salesMix($ranges['current']),
            'paymentMethods' => $overview['payment_methods'],
            'team' => array_map(fn (array $cashier): array => [
                ...$cashier,
                'initials' => (new User)->forceFill(['name' => $cashier['cashier_name']])->initials(),
            ], $overview['cashiers']),
            'topCustomers' => $canViewFinance ? $this->topCustomers($ranges['current']) : null,
            'recentActivity' => $this->recentActivity($canViewJobs),
            'lowStock' => $this->lowStock($counts['low_stock']),
        ];
    }

    /**
     * Live counts shared by the attention strip, KPI tiles and workshop.
     *
     * @return array{active_repairs: int, overdue_repairs: int, ready_for_pickup: int, low_stock: int, unsettled_bills: int, unsettled_amount: float}
     */
    private function counts(): array
    {
        $repairs = Job::query()
            ->whereIn('status', [...self::OPEN_JOB_STATUSES, 'done'])
            ->selectRaw('count(case when status in (?, ?) then 1 end) as active', self::OPEN_JOB_STATUSES)
            ->selectRaw('count(case when status in (?, ?) and expected_delivery_date < ? then 1 end) as overdue', [...self::OPEN_JOB_STATUSES, CarbonImmutable::today()->toDateString()])
            ->selectRaw("count(case when status = 'done' then 1 end) as ready")
            ->toBase()
            ->first();

        $unsettled = $this->unsettledBills()->selectRaw('count(*) as bills, coalesce(sum(total_amount), 0) as amount')->toBase()->first();

        return [
            'active_repairs' => (int) $repairs->active,
            'overdue_repairs' => (int) $repairs->overdue,
            'ready_for_pickup' => (int) $repairs->ready,
            'low_stock' => $this->lowStockProducts()->count(),
            'unsettled_bills' => (int) $unsettled->bills,
            'unsettled_amount' => SupplierService::cents($unsettled->amount) / 100,
        ];
    }

    /**
     * The revenue hero card: revenue, orders, average ticket and items sold against the previous period, the
     * repairs share, and the trend series (hourly for Today, daily otherwise).
     *
     * @param  array{current: array{from: CarbonImmutable, to: CarbonImmutable}, previous: array{from: CarbonImmutable, to: CarbonImmutable}}  $ranges
     * @param  array{revenue: float, sales_count: int}  $overview
     * @param  array{revenue: float, sales_count: int}  $previous
     * @return array<string, mixed>
     */
    private function revenue(array $ranges, string $period, array $overview, array $previous): array
    {
        $averageTicket = $overview['sales_count'] === 0 ? 0.0 : round($overview['revenue'] / $overview['sales_count'], 2);
        $previousAverageTicket = $previous['sales_count'] === 0 ? 0.0 : round($previous['revenue'] / $previous['sales_count'], 2);
        $itemsSold = $this->itemsSold($ranges['current']);
        $previousItemsSold = $this->itemsSold($ranges['previous']);

        return [
            'amount' => $overview['revenue'],
            'delta' => self::delta($overview['revenue'], $previous['revenue']),
            'orders' => $overview['sales_count'],
            'orders_delta' => self::delta($overview['sales_count'], $previous['sales_count']),
            'average_ticket' => $averageTicket,
            'average_ticket_delta' => self::delta($averageTicket, $previousAverageTicket),
            'items_sold' => $itemsSold,
            'items_sold_delta' => self::delta($itemsSold, $previousItemsSold),
            'repairs' => SupplierService::cents($this->sales($ranges['current'])->whereNotNull('job_id')->sum('total_amount')) / 100,
            'trend' => $this->trend($ranges, $period === 'today'),
        ];
    }

    /**
     * The trend chart points: one per hour of the day, or one per day, each paired with the same slot of the
     * previous period.
     *
     * @param  array{current: array{from: CarbonImmutable, to: CarbonImmutable}, previous: array{from: CarbonImmutable, to: CarbonImmutable}}  $ranges
     * @return list<array{tick: string, label: string, previous_label: string, current: float, previous: float}>
     */
    private function trend(array $ranges, bool $hourly): array
    {
        $current = $this->revenueBuckets($ranges['current'], $hourly);
        $previous = $this->revenueBuckets($ranges['previous'], $hourly);
        $points = [];

        if ($hourly) {
            foreach (range(0, 23) as $hour) {
                $slot = $ranges['current']['from']->setHour($hour);

                $points[] = [
                    'tick' => $slot->format('g A'),
                    'label' => $slot->format('M j, g A'),
                    'previous_label' => $ranges['previous']['from']->setHour($hour)->format('M j, g A'),
                    'current' => $current[$hour] ?? 0.0,
                    'previous' => $previous[$hour] ?? 0.0,
                ];
            }

            return $points;
        }

        foreach (CarbonPeriod::create($ranges['current']['from'], '1 day', $ranges['current']['to']->startOfDay()) as $index => $day) {
            $previousDay = $ranges['previous']['from']->addDays($index);

            $points[] = [
                'tick' => $day->format('M j'),
                'label' => $day->format('D, M j'),
                'previous_label' => $previousDay->format('D, M j'),
                'current' => $current[$day->toDateString()] ?? 0.0,
                'previous' => $previous[$previousDay->toDateString()] ?? 0.0,
            ];
        }

        return $points;
    }

    /**
     * Revenue per hour (0–23) or per date (Y-m-d) in the range.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return array<int|string, float>
     */
    private function revenueBuckets(array $range, bool $hourly): array
    {
        $bucket = $hourly ? $this->hourExpression() : 'date(created_at)';

        return $this->sales($range)
            ->selectRaw("{$bucket} as bucket, sum(total_amount) as total")
            ->groupByRaw($bucket)
            ->toBase()
            ->pluck('total', 'bucket')
            ->map(fn (mixed $total): float => SupplierService::cents($total) / 100)
            ->all();
    }

    /**
     * The Profit & Loss card, with each bar scaled to revenue.
     *
     * @param  array{revenue: float, cogs: float, gross_profit: float, expenses: float, net_profit: float, margin: ?float}  $overview
     * @param  array{net_profit: float}  $previous
     * @return array<string, mixed>
     */
    private function profitAndLoss(array $overview, array $previous): array
    {
        $revenue = $overview['revenue'];
        $scale = fn (float $amount): float => $revenue <= 0 ? 0.0 : round(min(100, max(0, $amount) / $revenue * 100), 1);

        return [
            'net_profit' => $overview['net_profit'],
            'delta' => self::delta($overview['net_profit'], $previous['net_profit']),
            'bars' => [
                ['label' => 'Revenue', 'amount' => $revenue, 'color' => '#0a0a0a', 'width' => $revenue > 0 ? 100.0 : 0.0],
                ['label' => 'Cost of goods', 'amount' => $overview['cogs'], 'color' => '#a1a1aa', 'width' => $scale($overview['cogs'])],
                ['label' => 'Gross profit', 'amount' => $overview['gross_profit'], 'color' => '#16a34a', 'width' => $scale($overview['gross_profit'])],
                ['label' => 'Expenses', 'amount' => $overview['expenses'], 'color' => '#e30613', 'width' => $scale($overview['expenses'])],
            ],
            'gross_margin' => $overview['margin'],
            'expense_ratio' => $revenue <= 0 ? null : round($overview['expenses'] / $revenue * 100, 1),
        ];
    }

    /**
     * The KPI tiles: the repair / money tiles for users who can see jobs and finance, otherwise the plain counts.
     * `compare` marks the tiles that show a change against the previous period.
     *
     * @param  array{current: array{from: CarbonImmutable, to: CarbonImmutable}, previous: array{from: CarbonImmutable, to: CarbonImmutable}}  $ranges
     * @param  array{active_repairs: int, overdue_repairs: int, unsettled_bills: int, unsettled_amount: float}  $counts
     * @param  array{sales_count: int}  $overview
     * @param  array{sales_count: int}  $previous
     * @return list<array{label: string, icon: string, value: string, compare: bool, delta: ?float, hint: string, hint_class: string}>
     */
    private function kpis(array $ranges, array $counts, array $overview, array $previous, bool $full): array
    {
        if (! $full) {
            $products = Product::query()->where('active', true)->count();
            $customers = Customer::query()->count();

            return [
                $this->tile('Orders', 'shopping-bag', $overview['sales_count'], 'Sales this period', delta: self::delta($overview['sales_count'], $previous['sales_count'])),
                $this->tile('Products', 'package', $products, 'In inventory'),
                $this->tile('Customers', 'users', $customers, 'Registered'),
            ];
        }

        $jobsReceived = $this->createdIn(Job::query(), $ranges['current'])->count();
        $previousJobsReceived = $this->createdIn(Job::query(), $ranges['previous'])->count();
        $newCustomers = $this->createdIn(Customer::query(), $ranges['current'])->count();
        $previousNewCustomers = $this->createdIn(Customer::query(), $ranges['previous'])->count();

        return [
            $this->tile(
                'Active repairs',
                'wrench',
                $counts['active_repairs'],
                number_format($counts['overdue_repairs']).' overdue',
                hintClass: $counts['overdue_repairs'] > 0 ? 'text-brand' : 'text-zinc-400',
            ),
            $this->tile('Jobs received', 'clipboard-list', $jobsReceived, 'New job notes', delta: self::delta($jobsReceived, $previousJobsReceived)),
            $this->tile('New customers', 'user-plus', $newCustomers, 'Added this period', delta: self::delta($newCustomers, $previousNewCustomers)),
            $this->tile('Unsettled bills', 'receipt', $counts['unsettled_bills'], Money::format($counts['unsettled_amount']).' billed'),
        ];
    }

    /**
     * One KPI tile. Pass `$delta` (even null, meaning "New") to compare it with the previous period.
     *
     * @return array{label: string, icon: string, value: string, compare: bool, delta: ?float, hint: string, hint_class: string}
     */
    private function tile(string $label, string $icon, int $value, string $hint, string $hintClass = 'text-zinc-400', float|false|null $delta = false): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'value' => number_format($value),
            'compare' => $delta !== false,
            'delta' => $delta === false ? null : $delta,
            'hint' => $hint,
            'hint_class' => $hintClass,
        ];
    }

    /**
     * The Repair Workshop card: the pipeline (live counts for open stages, period counts for closed ones),
     * turnaround, fix rate, devices waiting for pickup, technician workload and device types received.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @param  array{ready_for_pickup: int}  $counts
     * @return array<string, mixed>
     */
    private function workshop(array $range, array $counts): array
    {
        $open = Job::query()
            ->whereIn('status', [...self::OPEN_JOB_STATUSES, 'done'])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $delivered = Job::query()->where('status', 'delivered')->whereBetween('date_returned', [$range['from'], $range['to']])->get(['created_at', 'date_returned']);
        $unrepairable = Job::query()->where('status', 'unrepairable')->whereBetween('updated_at', [$range['from'], $range['to']])->count();
        $closed = $delivered->count() + $unrepairable;

        $stageCounts = [
            'pending' => (int) ($open['pending'] ?? 0),
            'ongoing' => (int) ($open['ongoing'] ?? 0),
            'done' => (int) ($open['done'] ?? 0),
            'delivered' => $delivered->count(),
            'unrepairable' => $unrepairable,
        ];
        $stageTotal = array_sum($stageCounts);

        $technicians = Job::query()
            ->whereIn('status', self::OPEN_JOB_STATUSES)
            ->selectRaw('assigned_technician_name as name, count(*) as total')
            ->groupBy('assigned_technician_name')
            ->orderByDesc('total')
            ->toBase()
            ->get();
        $busiest = (int) $technicians->max('total');

        return [
            'pipeline' => array_map(fn (string $status): array => [
                ...self::PIPELINE[$status],
                'status' => $status,
                'count' => $stageCounts[$status],
                'width' => $stageTotal === 0 ? 0.0 : round($stageCounts[$status] / $stageTotal * 100, 2),
            ], array_keys(self::PIPELINE)),
            'pipeline_total' => $stageTotal,
            'average_turnaround' => $delivered->isEmpty() ? null : round($delivered->avg(
                fn (Job $job): float => $job->created_at->diffInSeconds($job->date_returned, true) / 86400,
            ), 1),
            'delivered' => $delivered->count(),
            'fix_rate' => $closed === 0 ? null : round($delivered->count() / $closed * 100, 1),
            'waiting' => $this->waitingForPickup(),
            'waiting_total' => $counts['ready_for_pickup'],
            'technicians' => $technicians->map(fn (object $row): array => [
                'name' => filled($row->name) ? $row->name : 'Unassigned',
                'count' => (int) $row->total,
                'width' => $busiest === 0 ? 0 : round($row->total / $busiest * 100, 1),
            ])->all(),
            'device_types' => $this->createdIn(Job::query(), $range)
                ->selectRaw('device_type, count(*) as total')
                ->groupBy('device_type')
                ->orderByDesc('total')
                ->toBase()
                ->pluck('total', 'device_type')
                ->map(fn (mixed $total): int => (int) $total)
                ->all(),
        ];
    }

    /**
     * Finished repairs waiting to be collected, longest wait first. The wait starts when the job was marked
     * Job Done (its latest "done" history row).
     *
     * @return list<array{job_no: string, customer_name: string, device: string, days: int}>
     */
    private function waitingForPickup(): array
    {
        return Job::query()
            ->where('status', 'done')
            ->select(['id', 'job_no', 'customer_name', 'device_type', 'device_type_other', 'brand', 'model', 'updated_at'])
            ->addSelect(['ready_at' => JobStatusHistory::query()
                ->selectRaw('max(created_at)')
                ->whereColumn('job_id', 'jobs.id')
                ->where('status', 'done')])
            ->orderBy('ready_at')
            ->orderBy('updated_at')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(fn (Job $job): array => [
                'job_no' => $job->job_no,
                'customer_name' => $job->customer_name,
                'device' => $this->deviceLabel($job),
                'days' => (int) floor(Carbon::parse($job->ready_at ?? $job->updated_at)->diffInDays(now(), true)),
            ])
            ->all();
    }

    /**
     * The Busiest Hours heatmap over the last 30 days: sales per weekday (Mon–Sun) and hour, with only the
     * hours that had sales as columns.
     *
     * @return array{hours: list<int>, rows: list<array{day: string, cells: list<array{hour: int, count: int, intensity: float}>}>, peak: ?array{day: string, hour: int, count: int}, total: int}
     */
    private function busiestHours(): array
    {
        $today = CarbonImmutable::today();
        $range = ['from' => $today->subDays(self::HEATMAP_DAYS - 1)->startOfDay(), 'to' => $today->endOfDay()];
        $weekday = $this->weekdayExpression();
        $hour = $this->hourExpression();

        $slots = $this->sales($range)
            ->selectRaw("{$weekday} as weekday, {$hour} as hour, count(*) as total")
            ->groupByRaw("{$weekday}, {$hour}")
            ->toBase()
            ->get();

        if ($slots->isEmpty()) {
            return ['hours' => [], 'rows' => [], 'peak' => null, 'total' => 0];
        }

        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $fullDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $grid = [];

        foreach ($slots as $slot) {
            $grid[(int) $slot->weekday][(int) $slot->hour] = (int) $slot->total;
        }

        $hours = range((int) $slots->min('hour'), (int) $slots->max('hour'));
        $busiest = (int) $slots->max('total');
        $peak = $slots->sortBy([['total', 'desc'], ['weekday', 'asc'], ['hour', 'asc']])->first();

        return [
            'hours' => $hours,
            'rows' => array_map(fn (int $weekdayIndex): array => [
                'day' => $days[$weekdayIndex],
                'cells' => array_map(fn (int $hourOfDay): array => [
                    'hour' => $hourOfDay,
                    'count' => $grid[$weekdayIndex][$hourOfDay] ?? 0,
                    'intensity' => round(($grid[$weekdayIndex][$hourOfDay] ?? 0) / $busiest, 3),
                ], $hours),
            ], array_keys($days)),
            'peak' => ['day' => $fullDays[(int) $peak->weekday], 'hour' => (int) $peak->hour, 'count' => (int) $peak->total],
            'total' => (int) $slots->sum('total'),
        ];
    }

    /**
     * Best-selling products by revenue, with quantity and margin on their real FIFO cost.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return list<array{product_name: string, qty: int, revenue: float, margin: ?float}>
     */
    private function topProducts(array $range): array
    {
        return $this->saleItems($range)
            ->selectRaw('sale_items.product_id, max(sale_items.product_name) as product_name, sum(sale_items.qty) as qty, sum(sale_items.line_total) as revenue, sum(sale_items.cost_price * sale_items.qty) as cost')
            ->groupBy('sale_items.product_id')
            ->orderByDesc('revenue')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->map(function (object $row): array {
                $revenueCents = SupplierService::cents($row->revenue);
                $costCents = SupplierService::cents($row->cost);

                return [
                    'product_name' => (string) $row->product_name,
                    'qty' => (int) $row->qty,
                    'revenue' => $revenueCents / 100,
                    'margin' => $revenueCents === 0 ? null : round(($revenueCents - $costCents) / $revenueCents * 100, 1),
                ];
            })
            ->all();
    }

    /**
     * Product sales by main category, largest first; anything past the fifth category is grouped as "Other".
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return array{segments: list<array{name: string, amount: float, percent: float, color: string}>, total: float}
     */
    private function salesMix(array $range): array
    {
        $rows = $this->saleItems($range)
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('main_categories', 'main_categories.id', '=', 'products.main_category_id')
            ->selectRaw('main_categories.id as category_id, max(main_categories.name) as name, sum(sale_items.line_total) as amount')
            ->groupBy('main_categories.id')
            ->orderByDesc('amount')
            ->get()
            ->map(fn (object $row): array => ['name' => $row->name ?? 'Uncategorised', 'cents' => SupplierService::cents($row->amount)]);

        $limit = count(self::MIX_COLORS) - 1;

        if ($rows->count() > $limit + 1) {
            $rows = $rows->take($limit)->push(['name' => 'Other', 'cents' => $rows->slice($limit)->sum('cents')]);
        }

        $totalCents = $rows->sum('cents');

        return [
            'segments' => $rows->values()->map(fn (array $row, int $index): array => [
                'name' => $row['name'],
                'amount' => $row['cents'] / 100,
                'percent' => $totalCents === 0 ? 0.0 : round($row['cents'] / $totalCents * 100, 1),
                'color' => self::MIX_COLORS[$index],
            ])->all(),
            'total' => $totalCents / 100,
        ];
    }

    /**
     * Registered customers who spent the most, plus how many sales were walk-ins.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return array{customers: list<array{customer_name: string, visits: int, spend: float}>, walk_ins: int}
     */
    private function topCustomers(array $range): array
    {
        return [
            'customers' => $this->sales($range)
                ->whereNotNull('customer_id')
                ->selectRaw('customer_id, max(customer_name) as customer_name, count(*) as visits, sum(total_amount) as spend')
                ->groupBy('customer_id')
                ->orderByDesc('spend')
                ->limit(self::LIST_LIMIT)
                ->toBase()
                ->get()
                ->map(fn (object $row): array => [
                    'customer_name' => (string) $row->customer_name,
                    'visits' => (int) $row->visits,
                    'spend' => SupplierService::cents($row->spend) / 100,
                ])
                ->all(),
            'walk_ins' => $this->sales($range)->whereNull('customer_id')->count(),
        ];
    }

    /**
     * The latest sales and (for users who can see jobs) job notes, newest first.
     *
     * @return list<array{type: string, number: string, title: string, subtitle: string, amount: ?float, status: ?string, at: CarbonInterface, ago: string}>
     */
    private function recentActivity(bool $includeJobs): array
    {
        $sales = Sale::query()
            ->whereNull('status')
            ->latest()
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['id', 'invoice_no', 'customer_name', 'cashier_name', 'job_no', 'total_amount', 'created_at'])
            ->map(fn (Sale $sale): array => [
                'type' => 'sale',
                'number' => $sale->invoice_no,
                'title' => "Sale {$sale->invoice_no}",
                'subtitle' => $sale->customer_name.' · '.($sale->job_no ? "Billed {$sale->job_no}" : $sale->cashier_name),
                'amount' => (float) $sale->total_amount,
                'status' => null,
                'at' => $sale->created_at,
            ]);

        $jobs = ! $includeJobs ? collect() : Job::query()
            ->latest()
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['id', 'job_no', 'customer_name', 'device_type', 'device_type_other', 'brand', 'model', 'status', 'created_at'])
            ->map(fn (Job $job): array => [
                'type' => 'job',
                'number' => $job->job_no,
                'title' => "Job {$job->job_no}",
                'subtitle' => $job->customer_name.' · '.$this->deviceLabel($job),
                'amount' => null,
                'status' => $job->status,
                'at' => $job->created_at,
            ]);

        return $sales->concat($jobs)
            ->sortByDesc(fn (array $activity): int => $activity['at']->getTimestamp())
            ->take(self::ACTIVITY_LIMIT)
            ->map(fn (array $activity): array => [...$activity, 'ago' => self::ago($activity['at'])])
            ->values()
            ->all();
    }

    /**
     * Active products at or below their alert level, emptiest first.
     *
     * @return array{products: list<array{name: string, sku: string, stock: int, alert: int, width: float}>, count: int, tracked: int}
     */
    private function lowStock(int $count): array
    {
        return [
            'products' => $this->lowStockProducts()
                ->orderBy('total_stock')
                ->orderBy('name')
                ->limit(self::LOW_STOCK_LIMIT)
                ->get(['id', 'name', 'sku', 'total_stock', 'low_stock_alert'])
                ->map(fn (Product $product): array => [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock' => (int) $product->total_stock,
                    'alert' => (int) $product->low_stock_alert,
                    'width' => $product->low_stock_alert <= 0 ? 0.0 : round(min(100, max(0, $product->total_stock) / $product->low_stock_alert * 100), 1),
                ])
                ->all(),
            'count' => $count,
            'tracked' => Product::query()->where('active', true)->count(),
        ];
    }

    /**
     * @return Builder<Product>
     */
    private function lowStockProducts(): Builder
    {
        return Product::query()->where('active', true)->whereColumn('total_stock', '<=', 'low_stock_alert');
    }

    /**
     * Non-cancelled bills with money still to collect.
     *
     * @return Builder<Sale>
     */
    private function unsettledBills(): Builder
    {
        return Sale::query()->whereNull('status')->whereIn('payment_status', self::UNSETTLED_PAYMENT_STATUSES);
    }

    /**
     * Units sold (product lines only) on non-cancelled sales in the range.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     */
    private function itemsSold(array $range): int
    {
        return (int) $this->saleItems($range)->sum('sale_items.qty');
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
     * Sale lines of non-cancelled sales in the range.
     *
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     */
    private function saleItems(array $range): QueryBuilder
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNull('sales.status')
            ->whereBetween('sales.created_at', [$range['from'], $range['to']]);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{from: CarbonImmutable, to: CarbonImmutable}  $range
     * @return Builder<TModel>
     */
    private function createdIn(Builder $query, array $range): Builder
    {
        return $query->whereBetween('created_at', [$range['from'], $range['to']]);
    }

    /**
     * "Laptop · Dell Inspiron", using the typed type for "Other".
     */
    private function deviceLabel(Job $job): string
    {
        $type = $job->device_type === 'Other' && filled($job->device_type_other) ? $job->device_type_other : $job->device_type;
        $name = trim("{$job->brand} {$job->model}");

        return $name === '' ? (string) $type : "{$type} · {$name}";
    }

    /**
     * SQL for the hour (0–23) of `created_at`.
     */
    private function hourExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "cast(strftime('%H', created_at) as integer)"
            : 'hour(created_at)';
    }

    /**
     * SQL for the weekday of `created_at`, 0 = Monday … 6 = Sunday.
     */
    private function weekdayExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "((cast(strftime('%w', created_at) as integer) + 6) % 7)"
            : 'weekday(created_at)';
    }
}
