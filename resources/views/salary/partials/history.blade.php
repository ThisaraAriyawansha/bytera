@php
    use App\Support\Money;

    $sectionClasses = 'mb-2 font-prata text-sm text-ink';
    $total = $payments->total();
@endphp

<div x-data="salaryView()">
    <form method="GET" action="{{ route('salary.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
        <input type="hidden" name="tab" value="history">
        <x-search-input placeholder="Search payment no., employee or period…" :value="$search" aria-label="Search salary payments" class="lg:max-w-sm lg:flex-1" />
        <x-date-range auto-submit />
        <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
    </form>

    <p class="mb-2 text-sm text-zinc-500">{{ number_format($total) }} {{ str('payment')->plural($total) }} in this period</p>

    <div class="nexora-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="whitespace-nowrap px-5 py-3">Payment No.</th>
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Period</th>
                        <th class="whitespace-nowrap px-5 py-3">Issued By</th>
                        <th class="px-5 py-3">Date</th>
                        <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-zinc-50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">
                                {{ $payment->payment_no }}
                                @if ($payment->shift_no)
                                    <span class="block text-xs font-normal text-zinc-400">Drawer {{ $payment->shift_no }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="text-ink">{{ $payment->user_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $payment->user_role }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3"><x-badge :variant="$payment->type === 'monthly' ? 'default' : 'info'">{{ $payment->typeLabel() }}</x-badge></td>
                            <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-ink">{{ Money::format($payment->amount) }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $payment->period_label }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $payment->issued_by_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $payment->created_at->format('M j, Y g:i A') }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                        x-on:click="show(@js(route('salary.show', $payment)))"
                                        title="View" aria-label="View {{ $payment->payment_no }}">
                                    <x-lucide-eye class="h-4 w-4" />
                                </button>
                                @if ($canDelete)
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="$dispatch('open-modal', {
                                                name: 'salary-delete',
                                                message: @js("Delete {$payment->payment_no} (".Money::format($payment->amount)." to {$payment->user_name})? Its salaries expense is removed, linked sales / jobs are freed".($payment->shift_no ? " and, if {$payment->shift_no} is still open, the cash goes back into its drawer" : '').". This can't be undone."),
                                                action: @js(route('salary.destroy', $payment)),
                                            })"
                                            title="Delete" aria-label="Delete {{ $payment->payment_no }}">
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-sm text-zinc-400">
                                {{ $search !== '' ? 'No payments match "'.$search.'" in this period.' : 'No salary payments in this period.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$payments" />
    </div>

    @if ($canDelete)
        <x-confirm-dialog name="salary-delete" title="Delete salary payment?" confirm-label="Delete" />
    @endif

    <x-modal name="salary-view" max-width="lg" x-model="viewOpen">
        <x-slot:title><span x-text="payment?.payment_no ?? 'Salary Payment'">Salary Payment</span></x-slot:title>

        <div class="space-y-5">
            <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
            <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
            <p x-show="viewErrors.form" x-text="viewErrors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

            <template x-if="payment">
                <div class="space-y-5">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-0.5 text-sm">
                            <dt class="text-zinc-500">Employee</dt><dd class="text-ink" x-text="`${payment.user_name} · ${payment.user_role}`"></dd>
                            <dt class="text-zinc-500">Type</dt><dd class="text-ink" x-text="payment.type_label"></dd>
                            <dt class="text-zinc-500">Period</dt><dd class="text-ink" x-text="payment.period_label"></dd>
                            <dt class="text-zinc-500">Issued</dt><dd class="text-ink" x-text="`${payment.date} by ${payment.issued_by_name}`"></dd>
                            <dt class="text-zinc-500">Expense</dt><dd class="text-ink" x-text="payment.expense_no || '—'"></dd>
                            <dt class="text-zinc-500">Drawer</dt><dd class="text-ink" x-text="payment.shift_no || '—'"></dd>
                        </dl>
                        <div class="shrink-0">
                            <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-on:click="emailPayslip()" x-bind:disabled="sending || ! payment.user_email">
                                <x-lucide-mail class="h-4 w-4" /> <span x-text="sending ? 'Sending…' : 'Email payslip'"></span>
                            </button>
                            <p class="mt-1 text-xs text-zinc-400" x-show="payment.email_sent_at" x-text="`Sent ${payment.email_sent_at}`"></p>
                        </div>
                    </div>

                    <div>
                        <h4 class="{{ $sectionClasses }}">How this amount was calculated</h4>
                        <dl class="space-y-1 rounded-lg border border-zinc-200 p-4 text-sm">
                            <template x-for="(line, index) in payment.calculation" :key="index">
                                <div class="flex justify-between gap-3"
                                     x-bind:class="index === payment.calculation.length - 1 && payment.calculation.length > 1 ? 'border-t border-zinc-200 pt-1 font-medium' : ''">
                                    <dt x-bind:class="index === payment.calculation.length - 1 && payment.calculation.length > 1 ? 'text-ink' : 'text-zinc-500'" x-text="line.label"></dt>
                                    <dd class="tabular-nums" x-text="money(line.amount)"></dd>
                                </div>
                            </template>
                        </dl>
                    </div>

                    <div x-show="payment.items.length > 0">
                        <h4 class="{{ $sectionClasses }}">Linked sales / jobs (<span x-text="payment.items.length"></span>)</h4>
                        <ul class="max-h-60 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 text-sm">
                            <template x-for="item in payment.items" :key="item.kind + item.id">
                                <li class="flex justify-between gap-3 px-4 py-2">
                                    <span class="flex items-center gap-2">
                                        <span class="badge" x-bind:class="item.kind === 'job' ? 'badge-info' : 'badge-default'" x-text="item.kind === 'job' ? 'Job' : 'Sale'"></span>
                                        <span class="font-medium text-ink" x-text="item.number"></span>
                                    </span>
                                    <span class="tabular-nums text-zinc-600" x-text="money(item.amount)"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div x-show="payment.note">
                        <h4 class="{{ $sectionClasses }}">Note</h4>
                        <p class="whitespace-pre-line text-sm text-ink" x-text="payment.note"></p>
                    </div>
                </div>
            </template>
        </div>
    </x-modal>
</div>
