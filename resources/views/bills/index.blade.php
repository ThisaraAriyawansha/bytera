@php
    use App\Models\Sale;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $sectionClasses = 'mb-2 font-prata text-sm text-ink';
    $total = $bills->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="billView()">
        <x-page-header title="Bills" :subtitle="'Every sale invoice, with print, email and reversal · '.number_format($total).' '.str('bill')->plural($total).' in this period'" />

        <x-flash />

        <form method="GET" action="{{ route('bills.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search invoice no. or customer…" :value="$search" aria-label="Search bills" class="lg:max-w-sm lg:flex-1" />
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Invoice</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Payment</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($bills as $bill)
                            @php $isCancelled = $bill->status === 'cancelled'; @endphp
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium {{ $isCancelled ? 'text-zinc-400 line-through' : 'text-ink' }}">{{ $bill->invoice_no }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-ink">{{ $bill->customer_name }}</div>
                                    @if (filled($bill->customer_phone))
                                        <div class="text-xs text-zinc-500">{{ $bill->customer_phone }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $bill->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $bill->paymentLabel() }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums {{ $isCancelled ? 'text-zinc-400' : 'text-ink' }}">{{ Money::format($bill->total_amount) }}</td>
                                <td class="px-5 py-3">
                                    @if ($isCancelled)
                                        <x-badge variant="danger">Cancelled</x-badge>
                                    @else
                                        <x-badge variant="success">{{ ucfirst($bill->payment_status) }}</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="show(@js(route('bills.show', $bill)))"
                                            title="View" aria-label="View {{ $bill->invoice_no }}">
                                        <x-lucide-eye class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No bills match "'.$search.'" in this period.' : 'No bills in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$bills" />
        </div>

        <x-modal name="bill-view" max-width="4xl" x-model="viewOpen">
            <x-slot:title>
                <span class="flex flex-wrap items-center gap-2">
                    <span x-text="bill?.invoice_no ?? 'Bill'">Bill</span>
                    <span x-show="bill?.is_cancelled" x-cloak class="badge badge-danger font-poppins">Cancelled</span>
                </span>
            </x-slot:title>

            <div class="space-y-5">
                <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                <p x-show="notice" x-text="notice" x-cloak class="no-print rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                <p x-show="viewErrors.form || viewErrors.bill" x-text="viewErrors.form || viewErrors.bill" x-cloak class="no-print rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                <template x-if="bill">
                    <div class="space-y-5">
                        {{-- Actions --}}
                        <div class="no-print flex flex-wrap gap-2">
                            <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="printBill()">
                                <x-lucide-printer class="h-4 w-4" /> Print
                            </button>
                            <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-bind:disabled="pdfBusy !== ''" x-on:click="downloadBill()">
                                <x-lucide-download class="h-4 w-4" /> <span x-text="pdfBusy === 'download' ? 'Preparing…' : 'Download'"></span>
                            </button>
                            <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-show="bill.urls.email" x-bind:disabled="pdfBusy !== ''" x-on:click="emailBill()">
                                <x-lucide-mail class="h-4 w-4" /> <span x-text="pdfBusy === 'email' ? 'Sending…' : 'Email'"></span>
                            </button>
                            @if ($canEdit)
                                <button type="button" class="nexora-btn nexora-btn-outline" x-show="! bill.is_cancelled && ! editForm" x-on:click="startEdit()">
                                    <x-lucide-pencil class="h-4 w-4" /> Edit Bill
                                </button>
                            @endif
                            @if ($canReverse)
                                <button type="button" class="nexora-btn nexora-btn-danger sm:ml-auto" x-show="! bill.is_cancelled && ! reverseForm" x-on:click="startReverse()">
                                    <x-lucide-undo-2 class="h-4 w-4" /> Reverse Bill
                                </button>
                            @endif
                        </div>

                        {{-- Cancellation info --}}
                        <div x-show="bill.is_cancelled" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <p class="font-medium">
                                Reversed <span x-text="bill.cancelled_at"></span> by <span x-text="bill.cancelled_by_name"></span>
                            </p>
                            <p class="mt-0.5 whitespace-pre-line" x-text="bill.cancel_reason"></p>
                        </div>

                        {{-- Reverse Bill --}}
                        @if ($canReverse)
                            <template x-if="reverseForm">
                                <form class="no-print space-y-3 rounded-lg border border-red-200 bg-red-50/60 p-4" x-on:submit.prevent="confirmReverse()">
                                    <h4 class="font-prata text-sm text-ink">Reverse <span x-text="bill.invoice_no"></span>?</h4>
                                    <p class="text-sm text-zinc-600">
                                        The bill stays listed, marked Cancelled. Its items go back into Showroom stock (the same batches and serial
                                        numbers), loyalty points are reversed, its warranties are removed and — if its shift is still open — its
                                        payments come off the shift totals. This can't be undone.
                                    </p>
                                    <div>
                                        <label for="bill-reverse-reason" class="{{ $labelClasses }}">Reason *</label>
                                        <textarea id="bill-reverse-reason" rows="2" class="nexora-input" maxlength="500" required autofocus
                                                  placeholder="Reason for reversing this bill (required)…" x-model="reverseForm.reason"></textarea>
                                        <p x-show="viewErrors.reason" x-text="viewErrors.reason" class="{{ $errorClasses }}"></p>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="reverseForm = null; viewErrors = {}" x-bind:disabled="reversing">Cancel</button>
                                        <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="reversing">
                                            <span x-text="reversing ? 'Reversing…' : 'Reverse Bill'"></span>
                                        </button>
                                    </div>
                                </form>
                            </template>
                        @endif

                        {{-- Edit Bill --}}
                        @if ($canEdit)
                            <template x-if="editForm">
                                <form class="no-print space-y-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveEdit()">
                                    <h4 class="font-prata text-sm text-ink">Edit Bill</h4>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="bill-edit-name" class="{{ $labelClasses }}">Customer name *</label>
                                            <input id="bill-edit-name" type="text" class="nexora-input" maxlength="100" required x-model="editForm.customer_name">
                                            <p x-show="viewErrors.customer_name" x-text="viewErrors.customer_name" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="bill-edit-phone" class="{{ $labelClasses }}">Phone</label>
                                            <input id="bill-edit-phone" type="tel" class="nexora-input" maxlength="30" x-model="editForm.customer_phone">
                                            <p x-show="viewErrors.customer_phone" x-text="viewErrors.customer_phone" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="bill-edit-email" class="{{ $labelClasses }}">Email</label>
                                            <input id="bill-edit-email" type="email" class="nexora-input" x-model="editForm.customer_email">
                                            <p x-show="viewErrors.customer_email" x-text="viewErrors.customer_email" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="bill-edit-method" class="{{ $labelClasses }}">Payment method</label>
                                            <select id="bill-edit-method" class="nexora-input disabled:bg-zinc-100 disabled:text-zinc-500" x-model="editForm.payment_method" x-bind:disabled="bill.is_split">
                                                @foreach (Sale::PAYMENT_METHODS as $method => $label)
                                                    <option value="{{ $method }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <p x-show="bill.is_split" class="mt-1 text-xs text-zinc-500">Split payment (<span x-text="bill.payment_label"></span>) — the method can't be changed.</p>
                                            <p x-show="viewErrors.payment_method" x-text="viewErrors.payment_method" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="bill-edit-note" class="{{ $labelClasses }}">Note</label>
                                            <textarea id="bill-edit-note" rows="2" class="nexora-input" maxlength="500" x-model="editForm.note"></textarea>
                                            <p x-show="viewErrors.note" x-text="viewErrors.note" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-zinc-500">Items, prices and totals can't be edited — reverse the bill and ring it up again instead. Changes are recorded in the audit log.</p>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="editForm = null; viewErrors = {}">Cancel</button>
                                        <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="editSaving">
                                            <span x-text="editSaving ? 'Saving…' : 'Save Changes'"></span>
                                        </button>
                                    </div>
                                </form>
                            </template>
                        @endif

                        {{-- Details --}}
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                <h4 class="{{ $sectionClasses }}">Customer</h4>
                                <p class="font-medium text-ink" x-text="bill.customer_name"></p>
                                <p class="text-zinc-600" x-show="bill.customer_phone" x-text="bill.customer_phone"></p>
                                <p class="break-all text-zinc-600" x-show="bill.customer_email" x-text="bill.customer_email"></p>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                <h4 class="{{ $sectionClasses }}">Invoice</h4>
                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                                    <dt class="text-zinc-500">Date</dt><dd class="text-ink" x-text="bill.date"></dd>
                                    <dt class="text-zinc-500">Cashier</dt><dd class="text-ink" x-text="bill.cashier_name"></dd>
                                    <dt class="text-zinc-500">Shift</dt><dd class="text-ink" x-text="bill.shift_no || '—'"></dd>
                                    <dt class="text-zinc-500">Job</dt><dd class="text-ink" x-text="bill.job_no || '—'"></dd>
                                </dl>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                <h4 class="{{ $sectionClasses }}">Payment</h4>
                                <ul class="space-y-0.5">
                                    <template x-for="leg in bill.payments" :key="leg.method">
                                        <li class="flex justify-between gap-3">
                                            <span class="text-zinc-500" x-text="leg.label"></span>
                                            <span class="tabular-nums text-ink" x-text="money(leg.amount)"></span>
                                        </li>
                                    </template>
                                    <li class="flex justify-between gap-3" x-show="bill.amount_tendered !== null">
                                        <span class="text-zinc-500">Tendered</span><span class="tabular-nums" x-text="money(bill.amount_tendered)"></span>
                                    </li>
                                    <li class="flex justify-between gap-3 text-green-700" x-show="bill.change_amount !== null">
                                        <span>Change</span><span class="tabular-nums" x-text="money(bill.change_amount)"></span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Items --}}
                        <div class="overflow-x-auto rounded-lg border border-zinc-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                        <th class="px-4 py-2.5">Item</th>
                                        <th class="px-4 py-2.5 text-right">Qty</th>
                                        <th class="px-4 py-2.5 text-right">Price</th>
                                        <th class="px-4 py-2.5 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    <template x-for="(service, index) in bill.services" :key="'service-' + index">
                                        <tr class="align-top hover:bg-zinc-50">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-ink" x-text="service.name"></div>
                                                <div class="text-xs text-zinc-500" x-text="service.job_no ? `Service · ${service.job_no}` : 'Service'"></div>
                                                <div class="text-xs text-green-700" x-show="service.is_free" x-text="service.free_reason ? `Free — ${service.free_reason}` : 'Free'"></div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">1</td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="service.is_free ? 'Free' : money(service.price)"></td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="service.is_free ? 'Free' : money(service.price)"></td>
                                        </tr>
                                    </template>
                                    <template x-for="item in bill.items" :key="item.id">
                                        <tr class="align-top hover:bg-zinc-50">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-ink" x-text="item.product_name"></div>
                                                <div class="text-xs text-zinc-500" x-text="item.sku"></div>
                                                <div class="text-xs text-zinc-500" x-show="item.serials.length > 0" x-text="`Serial: ${item.serials.join(', ')}`"></div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="item.qty"></td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                                                <span x-text="money(item.unit_price)"></span>
                                                <span class="block text-xs text-brand" x-show="item.discount > 0" x-text="`- ${money(item.discount)} each`"></span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(item.line_total)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Totals + note --}}
                        <div class="flex flex-col-reverse gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="text-sm" x-show="bill.note">
                                <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Note</h4>
                                <p class="whitespace-pre-line text-ink" x-text="bill.note"></p>
                            </div>
                            <dl class="w-full space-y-1 text-sm sm:ml-auto sm:w-64">
                                <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd class="tabular-nums" x-text="money(bill.subtotal)"></dd></div>
                                <div class="flex justify-between text-brand" x-show="bill.discount_amount > 0"><dt>Discount</dt><dd class="tabular-nums" x-text="'- ' + money(bill.discount_amount)"></dd></div>
                                <div class="flex justify-between text-purple-700" x-show="bill.points_redeemed > 0"><dt>Points Redeemed</dt><dd class="tabular-nums" x-text="'- ' + money(bill.points_redeemed)"></dd></div>
                                <div class="flex justify-between" x-show="bill.tax_amount > 0"><dt class="text-zinc-500">Tax</dt><dd class="tabular-nums" x-text="money(bill.tax_amount)"></dd></div>
                                <div class="flex justify-between border-t border-zinc-200 pt-1 font-medium"><dt>Total</dt><dd class="font-prata tabular-nums" x-text="money(bill.total_amount)"></dd></div>
                            </dl>
                        </div>

                        {{-- A4 bill (printed / downloaded / emailed) --}}
                        <div>
                            <h4 class="no-print {{ $sectionClasses }}">Bill (A4)</h4>
                            <div class="overflow-x-auto rounded-md border border-zinc-200 bg-zinc-100 p-2">
                                <div class="mx-auto w-[210mm] shadow-sm" x-html="billHtml"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </x-modal>
    </div>
</x-layouts.app>
