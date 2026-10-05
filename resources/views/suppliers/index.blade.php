@php
    use App\Models\Supplier;
    use App\Models\SupplierPayment;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $iconButtonClasses = 'rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand';
    $total = $suppliers->total();
    $outstandingTotal = $outstanding->sum(fn (Supplier $supplier): float => (float) $supplier->balance);
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="recordForm(@js([
             'modal' => 'supplier-form',
             'storeUrl' => route('suppliers.store'),
             'blank' => ['name' => '', 'phone' => '', 'email' => '', 'address' => ''],
         ]))">
        <div x-data="supplierView()">
            <x-page-header title="Suppliers"
                           :subtitle="number_format($total).' '.str('supplier')->plural($total).($search !== '' ? ' found' : '').' · '.Money::format($outstandingTotal).' outstanding'">
                <x-slot:actions>
                    <a href="{{ route('suppliers.payments.index') }}" class="nexora-btn nexora-btn-outline">
                        <x-lucide-file-text class="h-4 w-4" /> Payment Report
                    </a>
                    <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="openAdd()">
                        <x-lucide-plus class="h-4 w-4" /> Add Supplier
                    </button>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            {{-- Outstanding summary --}}
            @if ($outstanding->isNotEmpty())
                <div class="nexora-card mb-6 overflow-hidden">
                    <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-3">
                        <h2 class="font-prata text-base text-ink">Outstanding Balances</h2>
                        <span class="text-sm font-semibold tabular-nums text-brand">{{ Money::format($outstandingTotal) }}</span>
                    </div>
                    <div class="max-h-72 overflow-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    <th class="px-5 py-2.5">Supplier</th>
                                    <th class="px-5 py-2.5 text-right">Balance</th>
                                    <th class="whitespace-nowrap px-5 py-2.5">Last Payment</th>
                                    <th class="whitespace-nowrap px-5 py-2.5 text-right">Unpaid For</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($outstanding as $supplier)
                                    @php($days = $supplier->unpaidForDays())
                                    <tr class="cursor-pointer hover:bg-zinc-50" x-on:click="show(@js(route('suppliers.show', $supplier)))">
                                        <td class="px-5 py-2.5 font-medium text-ink">{{ $supplier->name }}</td>
                                        <td class="whitespace-nowrap px-5 py-2.5 text-right font-medium tabular-nums text-red-600">{{ Money::format($supplier->balance) }}</td>
                                        <td class="whitespace-nowrap px-5 py-2.5 text-zinc-600">{{ $supplier->last_payment_at?->format('M j, Y') ?? 'Never' }}</td>
                                        <td class="whitespace-nowrap px-5 py-2.5 text-right">
                                            <span @class(['tabular-nums', 'font-medium text-red-600' => $days > 30, 'text-zinc-600' => $days <= 30])>
                                                {{ $days }} {{ str('day')->plural($days) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <form method="GET" action="{{ route('suppliers.index') }}" class="mb-4 max-w-md">
                <x-search-input placeholder="Search by name or phone…" :value="$search" aria-label="Search suppliers" />
            </form>

            <div class="nexora-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-5 py-3">Name</th>
                                <th class="px-5 py-3">Phone</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Total Payable</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Amount Paid</th>
                                <th class="px-5 py-3 text-right">Balance</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($suppliers as $supplier)
                                @php($status = Supplier::STATUSES[$supplier->payment_status] ?? Supplier::STATUSES['paid'])
                                <tr class="hover:bg-zinc-50">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-ink">{{ $supplier->name }}</div>
                                        @if ($supplier->email)
                                            <div class="text-xs text-zinc-400">{{ $supplier->email }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $supplier->phone }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($supplier->total_payable) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($supplier->amount_paid) }}</td>
                                    <td @class(['whitespace-nowrap px-5 py-3 text-right font-medium tabular-nums', 'text-red-600' => $supplier->balance > 0, 'text-ink' => $supplier->balance <= 0])>
                                        {{ Money::format($supplier->balance) }}
                                    </td>
                                    <td class="px-5 py-3"><x-badge :variant="$status['variant']">{{ $status['label'] }}</x-badge></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <button type="button" class="{{ $iconButtonClasses }}"
                                                x-on:click="show(@js(route('suppliers.show', $supplier)))"
                                                title="View" aria-label="View {{ $supplier->name }}">
                                            <x-lucide-eye class="h-4 w-4" />
                                        </button>
                                        @if ($canEditContact)
                                            <button type="button" class="{{ $iconButtonClasses }}"
                                                    x-on:click="openEdit(@js([
                                                        'name' => $supplier->name,
                                                        'phone' => $supplier->phone,
                                                        'email' => $supplier->email,
                                                        'address' => $supplier->address,
                                                        'updateUrl' => route('suppliers.update', $supplier),
                                                    ]))"
                                                    title="Edit contact" aria-label="Edit {{ $supplier->name }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                        {{ $search !== '' ? 'No suppliers match "'.$search.'".' : 'No suppliers yet.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-pagination :paginator="$suppliers" />
            </div>

            {{-- Supplier view --}}
            <x-modal name="supplier-view" max-width="2xl" x-model="viewOpen">
                <x-slot:title><span x-text="supplier?.name ?? 'Supplier'">Supplier</span></x-slot:title>

                <div class="space-y-5">
                    <p x-show="loading && ! supplier" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                    <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                    <p x-show="generalError && ! paymentForm && ! editing" x-text="generalError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    <template x-if="supplier">
                        <div class="space-y-5">
                            {{-- Contact --}}
                            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                                    <dt class="text-zinc-500">Phone</dt>
                                    <dd class="text-ink" x-text="supplier.phone"></dd>
                                    <dt class="text-zinc-500">Email</dt>
                                    <dd class="break-all text-ink" x-text="supplier.email || '—'"></dd>
                                    <dt class="text-zinc-500">Address</dt>
                                    <dd class="text-ink" x-text="supplier.address || '—'"></dd>
                                    <dt class="text-zinc-500">Status</dt>
                                    <dd><span class="badge" x-bind:class="statusVariant(supplier.payment_status)" x-text="statusLabel(supplier.payment_status)"></span></dd>
                                </dl>
                                <div class="flex shrink-0 flex-wrap gap-2">
                                    @if ($canEditContact)
                                        <button type="button" class="nexora-btn nexora-btn-outline"
                                                x-on:click="viewOpen = false; openEdit({ ...supplier, updateUrl: url })">
                                            <x-lucide-pencil class="h-4 w-4" /> Edit
                                        </button>
                                    @endif
                                    @if ($canSendStatement)
                                        <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60"
                                                x-on:click="sendStatement()" x-bind:disabled="sending">
                                            <x-lucide-mail class="h-4 w-4" />
                                            <span x-text="sending ? 'Sending…' : 'Send Statement'"></span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                            @if ($canSendStatement)
                                <p class="-mt-3 text-xs text-zinc-400" x-show="supplier.last_statement_sent_at">
                                    Last statement sent <span x-text="supplier.last_statement_sent_at"></span>
                                </p>
                            @endif

                            {{-- Totals --}}
                            <div class="grid grid-cols-3 gap-2">
                                <div class="rounded-lg border border-zinc-200 p-3">
                                    <div class="text-xs text-zinc-500">Total Payable</div>
                                    <div class="font-semibold tabular-nums text-ink" x-text="money(supplier.total_payable)"></div>
                                </div>
                                <div class="rounded-lg border border-zinc-200 p-3">
                                    <div class="text-xs text-zinc-500">Amount Paid</div>
                                    <div class="font-semibold tabular-nums text-green-700" x-text="money(supplier.amount_paid)"></div>
                                </div>
                                <div class="rounded-lg border border-zinc-200 p-3">
                                    <div class="text-xs text-zinc-500">Balance</div>
                                    <div class="font-semibold tabular-nums" x-bind:class="supplier.balance > 0 ? 'text-red-600' : 'text-ink'" x-text="money(supplier.balance)"></div>
                                </div>
                            </div>

                            {{-- Record Payment --}}
                            @if ($canRecordPayment)
                                <div>
                                    <button type="button" class="nexora-btn nexora-btn-primary disabled:opacity-60"
                                            x-show="! paymentForm" x-on:click="startPayment()" x-bind:disabled="supplier.balance <= 0">
                                        <x-lucide-banknote class="h-4 w-4" /> Record Payment
                                    </button>
                                    <p class="mt-1 text-xs text-zinc-400" x-show="! paymentForm && supplier.balance <= 0">Nothing is owed to this supplier.</p>

                                    <template x-if="paymentForm">
                                        <form class="rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="savePayment()">
                                            <h4 class="mb-3 font-prata text-sm text-ink">Record Payment</h4>
                                            <p x-show="viewErrors.form" x-text="viewErrors.form" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label for="payment-amount" class="{{ $labelClasses }}">Amount *</label>
                                                    <input id="payment-amount" type="number" min="0.01" step="0.01" class="nexora-input" x-model="paymentForm.amount" required>
                                                    <p class="mt-1 text-xs text-zinc-500">Outstanding: <span x-text="money(supplier.balance)"></span></p>
                                                    <p x-show="viewErrors.amount" x-text="viewErrors.amount" class="{{ $errorClasses }}"></p>
                                                    <p x-show="! viewErrors.amount && paymentTooLarge" class="{{ $errorClasses }}">
                                                        Payment of <span x-text="money(paymentForm.amount)"></span> exceeds the outstanding balance of <span x-text="money(supplier.balance)"></span>
                                                    </p>
                                                </div>
                                                <div>
                                                    <label for="payment-method" class="{{ $labelClasses }}">Method *</label>
                                                    <select id="payment-method" class="nexora-input" x-model="paymentForm.method">
                                                        @foreach (SupplierPayment::METHODS as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    <p x-show="viewErrors.method" x-text="viewErrors.method" class="{{ $errorClasses }}"></p>
                                                </div>
                                                <div>
                                                    <label for="payment-reference" class="{{ $labelClasses }}">Reference</label>
                                                    <input id="payment-reference" type="text" class="nexora-input" maxlength="100" placeholder="e.g. cheque no" x-model="paymentForm.reference">
                                                    <p x-show="viewErrors.reference" x-text="viewErrors.reference" class="{{ $errorClasses }}"></p>
                                                </div>
                                                <div>
                                                    <label for="payment-note" class="{{ $labelClasses }}">Note</label>
                                                    <input id="payment-note" type="text" class="nexora-input" maxlength="500" x-model="paymentForm.note">
                                                    <p x-show="viewErrors.note" x-text="viewErrors.note" class="{{ $errorClasses }}"></p>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex justify-end gap-2">
                                                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="paymentForm = null; viewErrors = {}">Cancel</button>
                                                <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="viewSaving || paymentTooLarge">
                                                    <span x-text="viewSaving ? 'Saving…' : 'Record Payment'"></span>
                                                </button>
                                            </div>
                                        </form>
                                    </template>
                                </div>
                            @endif

                            {{-- Edit payment --}}
                            @if ($canEditPayment)
                                <template x-if="editing">
                                    <form class="rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveEdit()">
                                        <h4 class="mb-3 font-prata text-sm text-ink">Edit Payment <span x-text="editing.payment_no"></span></h4>
                                        <p x-show="viewErrors.form" x-text="viewErrors.form" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label for="edit-payment-amount" class="{{ $labelClasses }}">Amount *</label>
                                                <input id="edit-payment-amount" type="number" min="0.01" step="0.01" class="nexora-input" x-model="editing.amount" required>
                                                <p x-show="viewErrors.amount" x-text="viewErrors.amount" class="{{ $errorClasses }}"></p>
                                            </div>
                                            <div>
                                                <label for="edit-payment-method" class="{{ $labelClasses }}">Method *</label>
                                                <select id="edit-payment-method" class="nexora-input" x-model="editing.method">
                                                    @foreach (SupplierPayment::METHODS as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label for="edit-payment-reference" class="{{ $labelClasses }}">Reference</label>
                                                <input id="edit-payment-reference" type="text" class="nexora-input" maxlength="100" x-model="editing.reference">
                                            </div>
                                            <div>
                                                <label for="edit-payment-note" class="{{ $labelClasses }}">Note</label>
                                                <input id="edit-payment-note" type="text" class="nexora-input" maxlength="500" x-model="editing.note">
                                            </div>
                                        </div>
                                        <p class="mt-2 text-xs text-zinc-500">Changing the amount recalculates the supplier's balance. The change is recorded in the audit log.</p>
                                        <div class="mt-4 flex justify-end gap-2">
                                            <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="editing = null; viewErrors = {}">Cancel</button>
                                            <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="viewSaving">
                                                <span x-text="viewSaving ? 'Saving…' : 'Save Payment'"></span>
                                            </button>
                                        </div>
                                    </form>
                                </template>
                            @endif

                            {{-- Payment History --}}
                            <div>
                                <h4 class="mb-2 font-prata text-sm text-ink">Payment History</h4>
                                <div class="overflow-x-auto rounded-lg border border-zinc-200">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                                <th class="whitespace-nowrap px-4 py-2.5">Payment No.</th>
                                                <th class="px-4 py-2.5">Date</th>
                                                <th class="px-4 py-2.5 text-right">Amount</th>
                                                <th class="px-4 py-2.5">Method</th>
                                                <th class="whitespace-nowrap px-4 py-2.5 text-right">Balance After</th>
                                                @if ($canEditPayment)
                                                    <th class="relative px-4 py-2.5 text-right"><span class="sr-only">Actions</span></th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100">
                                            <tr x-show="payments.length === 0">
                                                <td colspan="6" class="px-4 py-6 text-center text-zinc-400">No payments recorded yet.</td>
                                            </tr>
                                            <template x-for="payment in payments" x-bind:key="payment.id">
                                                <tr class="hover:bg-zinc-50" x-bind:class="editing?.id === payment.id && 'bg-brand-light'">
                                                    <td class="whitespace-nowrap px-4 py-2.5 font-medium text-ink">
                                                        <span x-text="payment.payment_no"></span>
                                                        <div class="text-xs font-normal text-zinc-400" x-show="payment.reference || payment.note"
                                                             x-text="[payment.reference, payment.note].filter(Boolean).join(' · ')"></div>
                                                    </td>
                                                    <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600" x-text="payment.date"></td>
                                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(payment.amount)"></td>
                                                    <td class="whitespace-nowrap px-4 py-2.5 text-zinc-600" x-text="payment.method_label"></td>
                                                    <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(payment.balance_after)"></td>
                                                    @if ($canEditPayment)
                                                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                                            <button type="button" class="{{ $iconButtonClasses }}" x-on:click="startEdit(payment)"
                                                                    title="Edit payment" x-bind:aria-label="`Edit payment ${payment.payment_no}`">
                                                                <x-lucide-pencil class="h-4 w-4" />
                                                            </button>
                                                        </td>
                                                    @endif
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </x-modal>
        </div>

        {{-- Add / Edit supplier contact --}}
        <x-modal name="supplier-form">
            <x-slot:title><span x-text="isEditing ? 'Edit Supplier' : 'Add Supplier'">Add Supplier</span></x-slot:title>

            <form id="supplier-form" class="space-y-4" x-on:submit.prevent="save()">
                <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div>
                    <label for="supplier-name" class="{{ $labelClasses }}">Name *</label>
                    <input id="supplier-name" type="text" class="nexora-input" maxlength="150" autocomplete="off" x-model="form.name" required autofocus>
                    <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="supplier-phone" class="{{ $labelClasses }}">Phone *</label>
                        <input id="supplier-phone" type="tel" class="nexora-input" maxlength="30" autocomplete="off" x-model="form.phone" required>
                        <p x-show="errors.phone" x-text="errors.phone" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="supplier-email" class="{{ $labelClasses }}">Email</label>
                        <input id="supplier-email" type="email" class="nexora-input" autocomplete="off" x-model="form.email">
                        <p x-show="errors.email" x-text="errors.email" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
                <div>
                    <label for="supplier-address" class="{{ $labelClasses }}">Address</label>
                    <textarea id="supplier-address" rows="2" class="nexora-input" maxlength="500" x-model="form.address"></textarea>
                    <p x-show="errors.address" x-text="errors.address" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <p class="text-xs text-zinc-500" x-show="isEditing">Only contact details change here. Balances come from GRNs and payments.</p>
            </form>

            <x-slot:footer>
                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'supplier-form')">Cancel</button>
                <button type="submit" form="supplier-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                    <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Supplier'">Save</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.app>
