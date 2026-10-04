@php
    use App\Models\Supplier;
    use App\Models\SupplierPayment;
    use App\Support\Money;
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="Supplier Payment Report" subtitle="Payments made to suppliers and the balances still owed.">
            <x-slot:actions>
                <a href="{{ route('suppliers.index') }}" class="nexora-btn nexora-btn-outline">
                    <x-lucide-arrow-left class="h-4 w-4" /> Suppliers
                </a>
                <a href="{{ route('suppliers.payments.export', request()->only(['from', 'to', 'search'])) }}" class="nexora-btn nexora-btn-primary">
                    <x-lucide-download class="h-4 w-4" /> Export CSV
                </a>
            </x-slot:actions>
        </x-page-header>

        <form method="GET" action="{{ route('suppliers.payments.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search payment no. or supplier…" :value="$search" aria-label="Search payments" class="lg:max-w-sm lg:flex-1" />
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card mb-6 overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-3">
                <h2 class="font-prata text-base text-ink">Payments</h2>
                <span class="text-sm text-zinc-500">Total paid <span class="font-semibold tabular-nums text-ink">{{ Money::format($paymentsTotal) }}</span></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="whitespace-nowrap px-5 py-3">Payment No.</th>
                            <th class="px-5 py-3">Supplier</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="whitespace-nowrap px-5 py-3">Paid By</th>
                            <th class="px-5 py-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($payments as $payment)
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">
                                    {{ $payment->payment_no }}
                                    @if ($payment->reference)
                                        <div class="text-xs font-normal text-zinc-400">{{ $payment->reference }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-zinc-600">{{ $payment->supplier_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($payment->amount) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ SupplierPayment::METHODS[$payment->method] ?? $payment->method }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $payment->paid_by_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $payment->created_at->format('M j, Y g:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-zinc-400">No supplier payments in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$payments" />
        </div>

        <div class="nexora-card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-3">
                <h2 class="font-prata text-base text-ink">Outstanding Balances</h2>
                <span class="text-sm font-semibold tabular-nums text-brand">{{ Money::format($balances->sum(fn (Supplier $supplier): float => (float) $supplier->balance)) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Supplier</th>
                            <th class="whitespace-nowrap px-5 py-3 text-right">Total Payable</th>
                            <th class="whitespace-nowrap px-5 py-3 text-right">Amount Paid</th>
                            <th class="px-5 py-3 text-right">Balance</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($balances as $supplier)
                            @php($status = Supplier::STATUSES[$supplier->payment_status] ?? Supplier::STATUSES['paid'])
                            <tr class="hover:bg-zinc-50">
                                <td class="px-5 py-3 font-medium text-ink">{{ $supplier->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($supplier->total_payable) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($supplier->amount_paid) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-medium tabular-nums text-red-600">{{ Money::format($supplier->balance) }}</td>
                                <td class="px-5 py-3"><x-badge :variant="$status['variant']">{{ $status['label'] }}</x-badge></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-400">Nothing is owed to any supplier.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
