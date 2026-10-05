@php
    use App\Support\Money;

    $kpis = [
        ['label' => 'Revenue', 'value' => Money::format($overview['revenue']), 'hint' => number_format($overview['sales_count']).' '.str('sale')->plural($overview['sales_count']), 'class' => 'text-ink'],
        ['label' => 'COGS', 'value' => Money::format($overview['cogs']), 'hint' => 'Cost of goods sold', 'class' => 'text-zinc-600'],
        ['label' => 'Gross Profit', 'value' => Money::format($overview['gross_profit']), 'hint' => 'Revenue − COGS', 'class' => $overview['gross_profit'] < 0 ? 'text-red-600' : 'text-green-700'],
        ['label' => 'Expenses', 'value' => Money::format($overview['expenses']), 'hint' => 'Incl. salaries', 'class' => 'text-brand'],
        ['label' => 'Net Profit', 'value' => Money::format($overview['net_profit']), 'hint' => 'Gross − Expenses', 'class' => $overview['net_profit'] < 0 ? 'text-red-600' : 'text-ink'],
        ['label' => 'Margin %', 'value' => $overview['margin'] === null ? '—' : number_format($overview['margin'], 1).'%', 'hint' => 'Gross ÷ Revenue', 'class' => ($overview['margin'] ?? 0) < 0 ? 'text-red-600' : 'text-ink'],
    ];
    $methodColors = ['cash' => 'bg-ink', 'card' => 'bg-brand', 'transfer' => 'bg-zinc-400', 'kokopay' => 'bg-purple-600'];
@endphp

<form method="GET" action="{{ route('finance.index') }}" class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end">
    <input type="hidden" name="tab" value="overview">
    <x-date-range auto-submit :from="$range['from']->toDateString()" :to="$range['to']->toDateString()" class="sm:max-w-md sm:flex-1" />
    <div class="flex gap-2">
        <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        <a href="{{ route('finance.export', ['tab' => 'overview', 'from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString()]) }}"
           class="nexora-btn nexora-btn-outline justify-center">
            <x-lucide-download class="h-4 w-4" /> Export CSV
        </a>
    </div>
</form>

{{-- KPI cards --}}
<div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
    @foreach ($kpis as $kpi)
        <div class="nexora-card px-4 py-3">
            <span class="block text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $kpi['label'] }}</span>
            <span class="block truncate font-prata text-xl tabular-nums {{ $kpi['class'] }}">{{ $kpi['value'] }}</span>
            <span class="block text-xs text-zinc-400">{{ $kpi['hint'] }}</span>
        </div>
    @endforeach
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Payment methods (split-aware) --}}
    <div class="nexora-card p-5">
        <h2 class="mb-4 font-prata text-base text-ink">Payment methods</h2>
        <ul class="space-y-3">
            @foreach ($overview['payment_methods'] as $method)
                <li>
                    <div class="mb-1 flex justify-between gap-3 text-sm">
                        <span class="text-ink">{{ $method['label'] }}</span>
                        <span class="tabular-nums text-zinc-600">{{ Money::format($method['amount']) }} · {{ number_format($method['percent'], 1) }}%</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100">
                        <div class="h-full rounded-full {{ $methodColors[$method['method']] ?? 'bg-zinc-400' }}" style="width: {{ $method['percent'] }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="mt-4 text-xs text-zinc-400">Split payments count each part under its own method.</p>
    </div>

    {{-- Cashier performance --}}
    <div class="nexora-card overflow-hidden">
        <h2 class="border-b border-zinc-200 px-5 py-3 font-prata text-base text-ink">Cashier performance</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="px-5 py-2.5">Cashier</th>
                        <th class="px-5 py-2.5 text-right">Sales</th>
                        <th class="px-5 py-2.5 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($overview['cashiers'] as $cashier)
                        <tr class="hover:bg-zinc-50">
                            <td class="px-5 py-2.5 font-medium text-ink">{{ $cashier['cashier_name'] }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($cashier['sales_count']) }}</td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums">{{ Money::format($cashier['revenue']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-zinc-400">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Supplier payables --}}
    <div class="nexora-card overflow-hidden lg:col-span-2">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-3">
            <h2 class="font-prata text-base text-ink">Supplier Payables</h2>
            <span class="text-sm font-semibold tabular-nums text-brand">{{ Money::format($overview['payables_total']) }}</span>
        </div>
        <div class="max-h-80 overflow-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="px-5 py-2.5">Supplier</th>
                        <th class="px-5 py-2.5 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($overview['payables'] as $supplier)
                        <tr class="hover:bg-zinc-50">
                            <td class="px-5 py-2.5 text-ink">{{ $supplier['name'] }}</td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right font-medium tabular-nums text-red-600">{{ Money::format($supplier['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-5 py-8 text-center text-zinc-400">Nothing is owed to suppliers.</td></tr>
                    @endforelse
                </tbody>
                @if ($overview['payables'] !== [])
                    <tfoot>
                        <tr class="border-t border-zinc-200 font-medium">
                            <td class="px-5 py-2.5 text-ink">Total</td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums text-red-600">{{ Money::format($overview['payables_total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
