@php
    $exportUrl = route('finance.export', ['tab' => 'daily', 'from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString()]);
@endphp

<div x-data="dailyBalance(@js(['days' => $days, 'exportUrl' => $exportUrl]))">
    <form method="GET" action="{{ route('finance.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
        <input type="hidden" name="tab" value="daily">
        <x-date-range auto-submit :from="$range['from']->toDateString()" :to="$range['to']->toDateString()" class="lg:max-w-md lg:flex-1" />
        <label class="lg:w-48">
            <span class="mb-1 block text-xs font-medium text-zinc-600">Opening balance (Rs.)</span>
            <input type="number" step="0.01" class="nexora-input" x-model="opening" x-on:change="saveOpening()" x-on:keydown.enter.prevent="saveOpening()">
        </label>
        <div class="flex gap-2">
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
            <a x-bind:href="exportUrl" href="{{ $exportUrl }}" class="nexora-btn nexora-btn-outline justify-center">
                <x-lucide-download class="h-4 w-4" /> Export CSV
            </a>
        </div>
    </form>
    <p class="-mt-2 mb-4 text-xs text-zinc-400">The opening balance is remembered in this browser.</p>

    <div class="nexora-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3 text-right">Income</th>
                        <th class="px-5 py-3 text-right">Expenses</th>
                        <th class="px-5 py-3 text-right">Net</th>
                        <th class="whitespace-nowrap px-5 py-3 text-right">Closing Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr class="bg-zinc-50/60">
                        <td class="px-5 py-2.5 text-zinc-500" colspan="4">Opening balance</td>
                        <td class="whitespace-nowrap px-5 py-2.5 text-right font-medium tabular-nums" x-text="money(opening)"></td>
                    </tr>
                    <template x-for="day in rows" :key="day.date">
                        <tr class="hover:bg-zinc-50" x-bind:class="day.income === 0 && day.expenses === 0 && 'text-zinc-400'">
                            <td class="whitespace-nowrap px-5 py-2.5" x-text="new Date(day.date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })"></td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums" x-text="money(day.income)"></td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums" x-bind:class="day.expenses > 0 && 'text-brand'" x-text="money(day.expenses)"></td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums" x-bind:class="day.net < 0 ? 'text-red-600' : (day.net > 0 ? 'text-green-700' : '')" x-text="money(day.net)"></td>
                            <td class="whitespace-nowrap px-5 py-2.5 text-right font-medium tabular-nums" x-bind:class="day.closing < 0 ? 'text-red-600' : 'text-ink'" x-text="money(day.closing)"></td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="border-t border-zinc-200 font-medium">
                        <td class="px-5 py-3 text-ink" colspan="4">Closing balance</td>
                        <td class="whitespace-nowrap px-5 py-3 text-right font-prata tabular-nums" x-bind:class="closingBalance < 0 ? 'text-red-600' : 'text-ink'" x-text="money(closingBalance)"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
