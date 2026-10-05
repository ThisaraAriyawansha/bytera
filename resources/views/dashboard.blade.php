@php
    use App\Models\Job;
    use App\Services\DashboardService;
    use App\Support\Money;

    $period = $dashboard['period'];
    $periodConfig = DashboardService::PERIODS[$period];
    $canViewFinance = $dashboard['canViewFinance'];
    $canViewJobs = $dashboard['canViewJobs'];
    $attention = $dashboard['attention'];
    $revenue = $dashboard['revenue'];
    $profit = $dashboard['profit'];
    $workshop = $dashboard['workshop'];
    $heatmap = $dashboard['heatmap'];
    $salesMix = $dashboard['salesMix'];
    $lowStock = $dashboard['lowStock'];

    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

    $cardTitle = 'font-prata text-base text-ink';
    $eyebrow = 'text-xs font-medium uppercase tracking-wider text-zinc-500';
    $hourLabel = fn (int $hourOfDay): string => now()->setTime($hourOfDay, 0)->format('ga');

    $pills = array_values(array_filter([
        $attention['overdue_repairs'] > 0 ? [
            'text' => number_format($attention['overdue_repairs']).' '.str('repair')->plural($attention['overdue_repairs']).' past promised date',
            'classes' => 'bg-red-50 text-red-700 ring-red-200',
            'icon' => 'alarm-clock',
            'url' => $canViewJobs ? route('jobs.index') : null,
        ] : null,
        $attention['ready_for_pickup'] > 0 ? [
            'text' => number_format($attention['ready_for_pickup']).' '.str('device')->plural($attention['ready_for_pickup']).' ready for pickup',
            'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'icon' => 'package-check',
            'url' => $canViewJobs ? route('jobs.index', ['status' => 'done']) : null,
        ] : null,
        $attention['low_stock'] > 0 ? [
            'text' => number_format($attention['low_stock']).' '.str('product')->plural($attention['low_stock']).' low on stock',
            'classes' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'icon' => 'triangle-alert',
            'url' => $canViewProducts ? route('products.index') : null,
        ] : null,
        $attention['unsettled_bills'] > 0 ? [
            'text' => number_format($attention['unsettled_bills']).' unsettled '.str('bill')->plural($attention['unsettled_bills']),
            'classes' => 'bg-zinc-100 text-zinc-700 ring-zinc-200',
            'icon' => 'receipt',
            'url' => $canViewBills ? route('bills.index') : null,
        ] : null,
    ]));
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        {{-- Header --}}
        <div class="mb-6 flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
            <div class="min-w-0">
                <p class="text-sm text-zinc-500"
                   x-data="{ greeting: @js($greeting) }"
                   x-init="const hour = new Date().getHours(); greeting = hour < 12 ? 'Good morning' : (hour < 17 ? 'Good afternoon' : 'Good evening')">
                    <span x-text="greeting">{{ $greeting }}</span> 👋
                </p>
                <h1 class="font-prata text-2xl text-ink">Business Overview</h1>
                <p class="text-sm text-zinc-500">{{ now()->format('l, F j, Y') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <nav class="inline-flex rounded-full bg-zinc-100 p-1" aria-label="Period">
                    @foreach (DashboardService::PERIODS as $key => $option)
                        <a href="{{ route('dashboard', ['period' => $key]) }}"
                           @class([
                               'rounded-full px-3.5 py-1.5 text-sm transition-colors',
                               'bg-white font-medium text-ink shadow-sm' => $key === $period,
                               'text-zinc-500 hover:text-ink' => $key !== $period,
                           ])
                           @if ($key === $period) aria-current="page" @endif>{{ $option['label'] }}</a>
                    @endforeach
                </nav>
                @if ($canSell)
                    <a href="{{ route('sales.index') }}" class="nexora-btn nexora-btn-primary">
                        <x-lucide-plus class="h-4 w-4" /> New Sale
                    </a>
                @endif
                @if ($canViewJobs)
                    <a href="{{ route('jobs.index', ['new' => 1]) }}" class="nexora-btn nexora-btn-outline">
                        <x-lucide-wrench class="h-4 w-4" /> New Job
                    </a>
                @endif
            </div>
        </div>

        {{-- 1. Attention strip --}}
        @if ($pills !== [])
            <div class="mb-6 flex flex-wrap gap-2">
                @foreach ($pills as $pill)
                    @php $pillClasses = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset '.$pill['classes']; @endphp
                    @if ($pill['url'])
                        <a href="{{ $pill['url'] }}" class="{{ $pillClasses }} hover:opacity-80">
                            <x-dynamic-component :component="'lucide-'.$pill['icon']" class="h-3.5 w-3.5" /> {{ $pill['text'] }}
                        </a>
                    @else
                        <span class="{{ $pillClasses }}">
                            <x-dynamic-component :component="'lucide-'.$pill['icon']" class="h-3.5 w-3.5" /> {{ $pill['text'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-6">
            @if ($canViewFinance)
                {{-- 2. Revenue hero --}}
                <section class="nexora-card flex min-w-0 flex-col p-5 lg:col-span-8"
                         style="background-image: radial-gradient(circle at 85% 15%, rgba(227, 6, 19, 0.09), rgba(227, 6, 19, 0.03) 35%, transparent 65%);"
                         x-data="revenueTrend(@js(['points' => $revenue['trend']]))">
                    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                        <div class="min-w-0">
                            <p class="{{ $eyebrow }}">Revenue · {{ $periodConfig['title'] }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="font-prata text-3xl tabular-nums text-ink">{{ Money::format($revenue['amount']) }}</span>
                                <x-delta-pill :delta="$revenue['delta']" />
                            </div>
                            <p class="text-xs text-zinc-400">{{ $periodConfig['comparison'] }}</p>
                        </div>

                        <dl class="grid grid-cols-3 gap-4 md:gap-6">
                            @foreach ([
                                ['label' => 'Orders', 'value' => number_format($revenue['orders']), 'delta' => $revenue['orders_delta']],
                                ['label' => 'Avg ticket', 'value' => Money::format(round($revenue['average_ticket'])), 'delta' => $revenue['average_ticket_delta']],
                                ['label' => 'Items sold', 'value' => number_format($revenue['items_sold']), 'delta' => $revenue['items_sold_delta']],
                            ] as $stat)
                                <div class="min-w-0">
                                    <dt class="text-xs text-zinc-500">{{ $stat['label'] }}</dt>
                                    <dd class="truncate font-medium tabular-nums text-ink">{{ $stat['value'] }}</dd>
                                    <dd><x-delta-pill :delta="$stat['delta']" size="sm" /></dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>

                    {{-- Trend chart --}}
                    <div class="relative mt-6 flex-1 touch-pan-y select-none" style="min-height: 9rem;" x-ref="plot"
                         x-on:pointermove="hover($event)" x-on:pointerdown="hover($event)" x-on:pointerleave="leave()">
                        <template x-for="line in gridLines" :key="line.label">
                            <div class="pointer-events-none absolute inset-x-0 border-t border-zinc-100" x-bind:style="`top: ${line.top}%`">
                                <span class="absolute -top-4 left-0 text-[10px] text-zinc-400" x-text="line.label"></span>
                            </div>
                        </template>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 border-t border-zinc-200"></div>

                        <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="absolute inset-0 h-full w-full overflow-visible" aria-hidden="true">
                            <defs>
                                <linearGradient id="revenue-trend-fill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#e30613" stop-opacity="0.28" />
                                    <stop offset="60%" stop-color="#e30613" stop-opacity="0.08" />
                                    <stop offset="100%" stop-color="#e30613" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <path x-bind:d="area" fill="url(#revenue-trend-fill)" />
                            <path x-bind:d="previousLine" fill="none" stroke="#a1a1aa" stroke-width="1.5" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                            <path x-bind:d="currentLine" fill="none" stroke="#e30613" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                        </svg>

                        <template x-if="activePoint">
                            <div class="pointer-events-none absolute inset-0">
                                <div class="absolute inset-y-0 w-px bg-zinc-300" x-bind:style="`left: ${x(active)}%`"></div>
                                <div class="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-brand shadow"
                                     x-bind:style="`left: ${x(active)}%; top: ${y(activePoint.current)}%`"></div>
                                <div class="absolute top-0 z-10 whitespace-nowrap rounded-md bg-ink px-3 py-2 text-xs text-white shadow-lg" x-bind:style="tooltipStyle">
                                    <p class="text-zinc-400" x-text="activePoint.label"></p>
                                    <p class="font-semibold tabular-nums" x-text="money(activePoint.current)"></p>
                                    <p class="tabular-nums text-zinc-400">prev <span x-text="money(activePoint.previous)"></span></p>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="relative mt-1 h-4 text-[10px] text-zinc-400" aria-hidden="true">
                        <template x-for="(tick, tickIndex) in ticks" :key="tick.index">
                            <span class="absolute whitespace-nowrap"
                                  x-bind:class="tickIndex === 0 ? '' : (tick.index === points.length - 1 ? '-translate-x-full' : '-translate-x-1/2')"
                                  x-bind:style="`left: ${tick.left}%`" x-text="tick.label"></span>
                        </template>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-500">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-brand"></span> This period</span>
                            <span class="inline-flex items-center gap-1.5"><span class="w-4 border-t-2 border-dashed border-zinc-400"></span> Previous period</span>
                        </div>
                        <span>Repairs: <span class="font-medium tabular-nums text-ink">{{ Money::format($revenue['repairs']) }}</span></span>
                    </div>
                </section>

                {{-- 3. Profit & Loss --}}
                <section class="nexora-card flex flex-col p-5 lg:col-span-4">
                    <h2 class="{{ $cardTitle }}">Profit &amp; Loss</h2>
                    <p class="mt-3 {{ $eyebrow }}">Net profit</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span @class(['font-prata text-2xl tabular-nums', 'text-red-600' => $profit['net_profit'] < 0, 'text-ink' => $profit['net_profit'] >= 0])>{{ Money::format($profit['net_profit']) }}</span>
                        <x-delta-pill :delta="$profit['delta']" />
                    </div>
                    <p class="text-xs text-zinc-400">{{ $periodConfig['comparison'] }}</p>

                    <ul class="mt-5 space-y-3">
                        @foreach ($profit['bars'] as $bar)
                            <li>
                                <div class="mb-1 flex justify-between gap-3 text-sm">
                                    <span class="text-zinc-600">{{ $bar['label'] }}</span>
                                    <span @class(['tabular-nums', 'text-red-600' => $bar['amount'] < 0, 'text-ink' => $bar['amount'] >= 0])>{{ Money::format($bar['amount']) }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                                    <div class="h-full rounded-full" style="width: {{ $bar['width'] }}%; background-color: {{ $bar['color'] }}"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <dl class="mt-auto grid grid-cols-2 gap-3 border-t border-zinc-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500">Gross margin</dt>
                            <dd class="font-medium tabular-nums text-ink">{{ $profit['gross_margin'] === null ? '—' : number_format($profit['gross_margin'], 1).'%' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">Expense ratio</dt>
                            <dd class="font-medium tabular-nums text-ink">{{ $profit['expense_ratio'] === null ? '—' : number_format($profit['expense_ratio'], 1).'%' }}</dd>
                        </div>
                    </dl>
                </section>
            @endif

            {{-- 4. KPI tiles --}}
            <div @class([
                'grid grid-cols-2 gap-4 lg:col-span-12 lg:gap-6',
                'lg:grid-cols-4' => count($dashboard['kpis']) === 4,
                'lg:grid-cols-3' => count($dashboard['kpis']) === 3,
            ])>
                @foreach ($dashboard['kpis'] as $kpi)
                    <div class="nexora-card min-w-0 px-4 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate {{ $eyebrow }}">{{ $kpi['label'] }}</span>
                            <x-dynamic-component :component="'lucide-'.$kpi['icon']" class="h-4 w-4 shrink-0 text-zinc-400" />
                        </div>
                        <span class="block font-prata text-2xl tabular-nums text-ink">{{ $kpi['value'] }}</span>
                        <div class="flex flex-wrap items-center gap-1.5 text-xs">
                            @if ($kpi['compare'])
                                <x-delta-pill :delta="$kpi['delta']" size="sm" />
                            @endif
                            <span class="truncate {{ $kpi['hint_class'] }}">{{ $kpi['hint'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- 5. Repair Workshop --}}
            @if ($workshop)
                <section class="nexora-card min-w-0 p-5 lg:col-span-12">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <h2 class="{{ $cardTitle }}">Repair Workshop</h2>
                        <a href="{{ route('jobs.index') }}" class="text-xs font-medium text-zinc-500 hover:text-brand">View jobs →</a>
                    </div>

                    <div class="flex h-3 overflow-hidden rounded-full bg-zinc-100">
                        @foreach ($workshop['pipeline'] as $stage)
                            @if ($stage['count'] > 0)
                                <div class="h-full" style="width: {{ $stage['width'] }}%; background-color: {{ $stage['color'] }}" title="{{ $stage['label'] }}: {{ $stage['count'] }}"></div>
                            @endif
                        @endforeach
                    </div>
                    <ul class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-5">
                        @foreach ($workshop['pipeline'] as $stage)
                            <li class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $stage['color'] }}"></span>
                                <span class="text-zinc-600">{{ $stage['label'] }}</span>
                                <span class="ml-auto font-medium tabular-nums text-ink sm:ml-0">{{ number_format($stage['count']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-1 text-[11px] text-zinc-400">Pending, In repair and Ready are live; Delivered and Unrepairable are for {{ str($periodConfig['title'])->lower() }}.</p>

                    <dl class="mt-4 grid grid-cols-3 gap-3 rounded-lg bg-zinc-50 p-3 text-sm">
                        <div>
                            <dt class="text-xs text-zinc-500">Avg turnaround</dt>
                            <dd class="font-medium tabular-nums text-ink">{{ $workshop['average_turnaround'] === null ? '—' : number_format($workshop['average_turnaround'], 1).' '.str('day')->plural((int) ceil($workshop['average_turnaround'])) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">Delivered</dt>
                            <dd class="font-medium tabular-nums text-ink">{{ number_format($workshop['delivered']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-zinc-500">Fix rate</dt>
                            <dd class="font-medium tabular-nums text-ink">{{ $workshop['fix_rate'] === null ? '—' : number_format($workshop['fix_rate'], 1).'%' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 grid gap-6 md:grid-cols-2">
                        <div class="min-w-0">
                            <h3 class="mb-2 {{ $eyebrow }}">Waiting for pickup <span class="text-zinc-400">({{ number_format($workshop['waiting_total']) }})</span></h3>
                            <ul class="divide-y divide-zinc-100">
                                @forelse ($workshop['waiting'] as $job)
                                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-ink">{{ $job['customer_name'] }}</p>
                                            <p class="truncate text-xs text-zinc-500">{{ $job['job_no'] }} · {{ $job['device'] }}</p>
                                        </div>
                                        <x-badge :variant="$job['days'] > 7 ? 'danger' : 'default'" class="shrink-0">
                                            {{ $job['days'] === 0 ? 'Today' : $job['days'].' '.str('day')->plural($job['days']) }}
                                        </x-badge>
                                    </li>
                                @empty
                                    <li class="py-4 text-sm text-zinc-400">No devices waiting for pickup.</li>
                                @endforelse
                            </ul>
                        </div>

                        <div class="min-w-0">
                            <h3 class="mb-2 {{ $eyebrow }}">Technician workload</h3>
                            <ul class="space-y-2.5">
                                @forelse ($workshop['technicians'] as $technician)
                                    <li>
                                        <div class="mb-1 flex justify-between gap-3 text-sm">
                                            <span class="truncate text-zinc-600">{{ $technician['name'] }}</span>
                                            <span class="tabular-nums text-ink">{{ number_format($technician['count']) }} open</span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-100">
                                            <div class="h-full rounded-full bg-ink" style="width: {{ $technician['width'] }}%"></div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="py-4 text-sm text-zinc-400">No open repairs.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    @if ($workshop['device_types'] !== [])
                        <div class="mt-5 flex flex-wrap items-center gap-1.5 border-t border-zinc-100 pt-4">
                            <span class="mr-1 text-xs text-zinc-500">Received:</span>
                            @foreach ($workshop['device_types'] as $deviceType => $count)
                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs text-zinc-700">
                                    {{ $deviceType }} <span class="font-medium text-ink">{{ $count }}</span>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            {{-- 6. Busiest Hours --}}
            <section @class(['nexora-card min-w-0 p-5', 'lg:col-span-7' => $canViewFinance, 'lg:col-span-12' => ! $canViewFinance])>
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="{{ $cardTitle }}">Busiest Hours</h2>
                    <span class="text-xs text-zinc-400">Last {{ DashboardService::HEATMAP_DAYS }} days</span>
                </div>

                @if ($heatmap['rows'] === [])
                    <p class="py-8 text-center text-sm text-zinc-400">No sales in the last {{ DashboardService::HEATMAP_DAYS }} days.</p>
                @else
                    <div class="overflow-x-auto">
                        <div class="grid gap-1" style="grid-template-columns: 2.25rem repeat({{ count($heatmap['hours']) }}, minmax(1.25rem, 1fr)); min-width: {{ 2.25 + count($heatmap['hours']) * 1.5 }}rem">
                            <span></span>
                            @foreach ($heatmap['hours'] as $hourOfDay)
                                <span class="text-center text-[10px] text-zinc-400">{{ count($heatmap['hours']) > 12 && $loop->index % 2 === 1 ? '' : $hourLabel($hourOfDay) }}</span>
                            @endforeach

                            @foreach ($heatmap['rows'] as $row)
                                <span class="self-center text-xs text-zinc-500">{{ $row['day'] }}</span>
                                @foreach ($row['cells'] as $cell)
                                    <span @class(['h-6 rounded-sm', 'bg-zinc-100' => $cell['count'] === 0])
                                          @if ($cell['count'] > 0) style="background-color: rgba(227, 6, 19, {{ round(0.15 + $cell['intensity'] * 0.85, 3) }})" @endif
                                          title="{{ $row['day'] }} {{ $hourLabel($cell['hour']) }} · {{ $cell['count'] }} {{ str('sale')->plural($cell['count']) }}"></span>
                                @endforeach
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-500">
                        <span>
                            Peak slot:
                            <span class="font-medium text-ink">{{ $heatmap['peak']['day'] }} {{ $hourLabel($heatmap['peak']['hour']) }}–{{ $hourLabel(($heatmap['peak']['hour'] + 1) % 24) }}</span>
                            · {{ number_format($heatmap['peak']['count']) }} {{ str('sale')->plural($heatmap['peak']['count']) }}
                        </span>
                        <span class="inline-flex items-center gap-1">
                            Less
                            <span class="h-3 w-3 rounded-sm bg-zinc-100"></span>
                            @foreach ([0, 0.33, 0.66, 1] as $step)
                                <span class="h-3 w-3 rounded-sm" style="background-color: rgba(227, 6, 19, {{ 0.15 + $step * 0.85 }})"></span>
                            @endforeach
                            More
                        </span>
                    </div>
                @endif
            </section>

            {{-- 7. Top Products --}}
            @if ($dashboard['topProducts'] !== null)
                <section class="nexora-card min-w-0 overflow-hidden lg:col-span-5">
                    <h2 class="border-b border-zinc-200 px-5 py-3 {{ $cardTitle }}">Top Products</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    <th class="relative w-10 px-4 py-2.5"><span class="sr-only">Rank</span></th>
                                    <th class="px-2 py-2.5">Product</th>
                                    <th class="px-2 py-2.5 text-right">Qty</th>
                                    <th class="px-2 py-2.5 text-right">Margin</th>
                                    <th class="px-4 py-2.5 text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @forelse ($dashboard['topProducts'] as $product)
                                    <tr class="hover:bg-zinc-50">
                                        <td class="px-4 py-2.5">
                                            <span @class([
                                                'inline-flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-semibold',
                                                'bg-brand text-white' => $loop->first,
                                                'bg-zinc-100 text-zinc-600' => ! $loop->first,
                                            ])>{{ $loop->iteration }}</span>
                                        </td>
                                        <td class="max-w-[12rem] truncate px-2 py-2.5 font-medium text-ink">{{ $product['product_name'] }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums">{{ number_format($product['qty']) }}</td>
                                        <td @class(['whitespace-nowrap px-2 py-2.5 text-right tabular-nums', 'text-red-600' => ($product['margin'] ?? 0) < 10, 'text-green-700' => ($product['margin'] ?? 0) >= 10])>
                                            {{ $product['margin'] === null ? '—' : number_format($product['margin'], 1).'%' }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums text-ink">{{ Money::format($product['revenue']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-8 text-center text-zinc-400">No products sold in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            {{-- 8. Sales Mix + payment methods --}}
            <section class="nexora-card min-w-0 p-5 lg:col-span-7">
                <h2 class="{{ $cardTitle }}">Sales Mix</h2>
                <p class="mb-4 text-xs text-zinc-400">Product sales by main category</p>

                @if ($salesMix['segments'] === [])
                    <p class="py-4 text-sm text-zinc-400">No products sold in this period.</p>
                @else
                    <div class="flex h-3 overflow-hidden rounded-full bg-zinc-100">
                        @foreach ($salesMix['segments'] as $segment)
                            <div class="h-full" style="width: {{ $segment['percent'] }}%; background-color: {{ $segment['color'] }}" title="{{ $segment['name'] }} · {{ number_format($segment['percent'], 1) }}%"></div>
                        @endforeach
                    </div>
                    <ul class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                        @foreach ($salesMix['segments'] as $segment)
                            <li class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                                <span class="min-w-0 flex-1 truncate text-zinc-600">{{ $segment['name'] }}</span>
                                <span class="tabular-nums text-zinc-500">{{ number_format($segment['percent'], 1) }}%</span>
                                @if ($canViewFinance)
                                    <span class="min-w-24 shrink-0 whitespace-nowrap text-right tabular-nums text-ink">{{ Money::format($segment['amount']) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h3 class="mb-2 mt-6 {{ $eyebrow }}">Payment methods</h3>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($dashboard['paymentMethods'] as $method)
                        <div class="rounded-lg border border-zinc-200 px-3 py-2">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-xs text-zinc-500">{{ $method['label'] }}</span>
                                <span class="text-xs font-medium tabular-nums text-ink">{{ number_format($method['percent'], 1) }}%</span>
                            </div>
                            <span class="block truncate text-sm font-medium tabular-nums text-ink">{{ Money::format($method['amount']) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-[11px] text-zinc-400">Split payments count each part under its own method.</p>
            </section>

            {{-- 9. Team --}}
            <section class="nexora-card min-w-0 p-5 lg:col-span-5">
                <h2 class="mb-3 {{ $cardTitle }}">Team</h2>
                <ul class="divide-y divide-zinc-100">
                    @forelse ($dashboard['team'] as $member)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ink font-prata text-xs text-white">{{ $member['initials'] }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink">{{ $member['cashier_name'] }}</p>
                                <p class="text-xs text-zinc-500">{{ number_format($member['sales_count']) }} {{ str('sale')->plural($member['sales_count']) }}</p>
                            </div>
                            <span class="whitespace-nowrap text-sm font-medium tabular-nums text-ink">{{ Money::format($member['revenue']) }}</span>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-zinc-400">No sales in this period.</li>
                    @endforelse
                </ul>
            </section>

            {{-- 10. Top Customers --}}
            @if ($dashboard['topCustomers'] !== null)
                <section class="nexora-card flex min-w-0 flex-col p-5 lg:col-span-4">
                    <h2 class="mb-3 {{ $cardTitle }}">Top Customers</h2>
                    <ul class="divide-y divide-zinc-100">
                        @forelse ($dashboard['topCustomers']['customers'] as $customer)
                            <li class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink">{{ $customer['customer_name'] }}</p>
                                    <p class="text-xs text-zinc-500">{{ number_format($customer['visits']) }} {{ str('visit')->plural($customer['visits']) }}</p>
                                </div>
                                <span class="whitespace-nowrap text-sm font-medium tabular-nums text-ink">{{ Money::format($customer['spend']) }}</span>
                            </li>
                        @empty
                            <li class="py-6 text-center text-sm text-zinc-400">No registered customers bought in this period.</li>
                        @endforelse
                    </ul>
                    @if ($dashboard['topCustomers']['walk_ins'] > 0)
                        <p class="mt-auto border-t border-zinc-100 pt-3 text-xs text-zinc-500">+ {{ number_format($dashboard['topCustomers']['walk_ins']) }} walk-in {{ str('sale')->plural($dashboard['topCustomers']['walk_ins']) }}</p>
                    @endif
                </section>
            @endif

            {{-- 11. Recent Activity --}}
            <section @class(['nexora-card min-w-0 p-5', 'lg:col-span-4' => $canViewFinance, 'lg:col-span-6' => ! $canViewFinance])>
                <h2 class="mb-3 {{ $cardTitle }}">Recent Activity</h2>
                <ul class="divide-y divide-zinc-100">
                    @forelse ($dashboard['recentActivity'] as $activity)
                        <li class="flex items-center gap-3 py-2.5">
                            <span @class([
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                                'bg-brand-light text-brand' => $activity['type'] === 'sale',
                                'bg-zinc-100 text-zinc-600' => $activity['type'] === 'job',
                            ])>
                                @if ($activity['type'] === 'sale')
                                    <x-lucide-receipt class="h-4 w-4" />
                                @else
                                    <x-lucide-wrench class="h-4 w-4" />
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink">{{ $activity['title'] }}</p>
                                <p class="truncate text-xs text-zinc-500">{{ $activity['subtitle'] }}</p>
                            </div>
                            <div class="shrink-0 text-right">
                                @if ($activity['amount'] !== null)
                                    <p class="text-sm font-medium tabular-nums text-ink">{{ Money::format($activity['amount']) }}</p>
                                @else
                                    <x-badge :variant="Job::STATUSES[$activity['status']]['variant'] ?? 'default'">{{ Job::STATUSES[$activity['status']]['label'] ?? $activity['status'] }}</x-badge>
                                @endif
                                <p class="text-[11px] text-zinc-400" title="{{ $activity['at']->format('M j, Y g:i A') }}">{{ $activity['ago'] }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-sm text-zinc-400">Nothing yet — sales and jobs will show up here.</li>
                    @endforelse
                </ul>
            </section>

            {{-- 12. Low Stock --}}
            <section @class(['nexora-card min-w-0 p-5', 'lg:col-span-4' => $canViewFinance, 'lg:col-span-6' => ! $canViewFinance])>
                <div class="mb-3 flex items-center justify-between gap-4">
                    <h2 class="{{ $cardTitle }}">Low Stock</h2>
                    @if ($canViewProducts && $lowStock['count'] > 0)
                        <a href="{{ route('products.index') }}" class="text-xs font-medium text-zinc-500 hover:text-brand">{{ number_format($lowStock['count']) }} total →</a>
                    @endif
                </div>

                @if ($lowStock['products'] === [])
                    <div class="flex flex-col items-center py-6 text-center">
                        <span class="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-green-50 text-green-600">
                            <x-lucide-check class="h-5 w-5" />
                        </span>
                        <p class="text-sm font-medium text-ink">All stocked up</p>
                        <p class="text-xs text-zinc-500">{{ number_format($lowStock['tracked']) }} {{ str('product')->plural($lowStock['tracked']) }} tracked</p>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach ($lowStock['products'] as $product)
                            <li>
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink">{{ $product['name'] }}</p>
                                        <p class="truncate text-xs text-zinc-500">{{ $product['sku'] }} · alert at {{ number_format($product['alert']) }}</p>
                                    </div>
                                    <x-badge :variant="$product['stock'] <= 0 ? 'danger' : 'warning'" class="shrink-0">{{ number_format($product['stock']) }} left</x-badge>
                                </div>
                                <div class="h-1 overflow-hidden rounded-full bg-zinc-100">
                                    <div @class(['h-full rounded-full', 'bg-red-500' => $product['stock'] <= 0, 'bg-amber-500' => $product['stock'] > 0]) style="width: {{ $product['width'] }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
