@php
    use App\Services\StockService;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $total = $grns->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="grnView()">
        <x-page-header title="GRN" :subtitle="'Goods received from suppliers · '.number_format($total).' '.str('GRN')->plural($total).' in this period'">
            @if ($canCreate)
                <x-slot:actions>
                    <a href="{{ route('grn.create') }}" class="nexora-btn nexora-btn-primary">
                        <x-lucide-package-plus class="h-4 w-4" /> New GRN
                    </a>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-flash />

        <form method="GET" action="{{ route('grn.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search GRN no. or supplier…" :value="$search" aria-label="Search GRNs" class="lg:max-w-sm lg:flex-1" />
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="whitespace-nowrap px-5 py-3">GRN No.</th>
                            <th class="px-5 py-3">Supplier</th>
                            <th class="px-5 py-3">Location</th>
                            <th class="whitespace-nowrap px-5 py-3">Received By</th>
                            <th class="whitespace-nowrap px-5 py-3 text-right">Total Cost</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($grns as $grn)
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $grn->grn_no }}</td>
                                <td class="px-5 py-3 text-zinc-600">{{ $grn->supplier_name ?: '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$grn->location === 'showroom' ? 'info' : 'default'">{{ StockService::LOCATIONS[$grn->location] ?? $grn->location }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $grn->received_by_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($grn->total_cost) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $grn->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="show(@js(route('grn.show', $grn)))"
                                            title="View" aria-label="View {{ $grn->grn_no }}">
                                        <x-lucide-eye class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No GRNs match "'.$search.'" in this period.' : 'No GRNs in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$grns" />
        </div>

        <x-modal name="grn-view" max-width="4xl" x-model="viewOpen">
            <x-slot:title><span x-text="grn?.grn_no ?? 'GRN'">GRN</span></x-slot:title>

            <div class="space-y-5">
                <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                <p x-show="viewErrors.form" x-text="viewErrors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                <template x-if="grn">
                    <div class="space-y-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                                <dt class="text-zinc-500">Supplier</dt>
                                <dd class="text-ink" x-text="grn.supplier_name || 'No supplier'"></dd>
                                <dt class="text-zinc-500">Location</dt>
                                <dd><span class="badge" x-bind:class="grn.location === 'showroom' ? 'badge-info' : 'badge-default'" x-text="grn.location_label"></span></dd>
                                <dt class="text-zinc-500">Received By</dt>
                                <dd class="text-ink" x-text="grn.received_by_name"></dd>
                                <dt class="text-zinc-500">Date</dt>
                                <dd class="text-ink" x-text="grn.date"></dd>
                                <dt class="text-zinc-500">Note</dt>
                                <dd class="text-ink" x-text="grn.note || '—'"></dd>
                            </dl>
                            @if ($canEdit)
                                <button type="button" class="nexora-btn nexora-btn-outline shrink-0" x-show="! editForm" x-on:click="startEdit()">
                                    <x-lucide-pencil class="h-4 w-4" /> Edit GRN
                                </button>
                            @endif
                        </div>

                        @if ($canEdit)
                            <template x-if="editForm">
                                <form class="space-y-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveEdit()">
                                    <h4 class="font-prata text-sm text-ink">Edit GRN</h4>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <span class="{{ $labelClasses }}">Supplier</span>
                                            <x-searchable-select :options="$supplierOptions" empty-label="No supplier" placeholder="No supplier"
                                                                 no-results="No suppliers found" x-model="editForm.supplier_id" />
                                            <p x-show="viewErrors.supplier_id" x-text="viewErrors.supplier_id" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="grn-edit-note" class="{{ $labelClasses }}">Note</label>
                                            <input id="grn-edit-note" type="text" class="nexora-input" maxlength="500" x-model="editForm.note">
                                            <p x-show="viewErrors.note" x-text="viewErrors.note" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </div>

                                    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                                    <th class="px-4 py-2.5">Product</th>
                                                    <th class="px-4 py-2.5 text-right">Qty</th>
                                                    <th class="whitespace-nowrap px-4 py-2.5">Cost Price *</th>
                                                    <th class="whitespace-nowrap px-4 py-2.5">Selling Price</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-100">
                                                <template x-for="(item, index) in editForm.items" x-bind:key="item.id">
                                                    <tr class="align-top">
                                                        <td class="px-4 py-2.5">
                                                            <div class="font-medium text-ink" x-text="grn.items[index].product_name"></div>
                                                            <div class="text-xs text-zinc-500" x-text="grn.items[index].sku"></div>
                                                        </td>
                                                        <td class="px-4 py-2.5 text-right tabular-nums text-zinc-500" x-text="grn.items[index].qty"></td>
                                                        <td class="min-w-[8rem] px-4 py-2.5">
                                                            <input type="number" min="0.01" step="0.01" class="nexora-input" required
                                                                   x-bind:aria-label="`Cost price of ${grn.items[index].product_name}`" x-model="item.cost_price">
                                                        </td>
                                                        <td class="min-w-[8rem] px-4 py-2.5">
                                                            <input type="number" min="0" step="0.01" class="nexora-input" placeholder="Use product price"
                                                                   x-bind:aria-label="`Selling price of ${grn.items[index].product_name}`" x-model="item.selling_price">
                                                            <p x-show="itemError(index)" x-text="itemError(index)" class="{{ $errorClasses }}"></p>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>

                                    <p class="text-xs text-zinc-500">
                                        Quantities and serials are locked. A cost change is also written to the item's stock batch.
                                        The GRN total and the supplier's balance are not recalculated. Changes are recorded in the audit log.
                                    </p>

                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="editForm = null; viewErrors = {}">Cancel</button>
                                        <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="viewSaving">
                                            <span x-text="viewSaving ? 'Saving…' : 'Save Changes'"></span>
                                        </button>
                                    </div>
                                </form>
                            </template>
                        @endif

                        <div class="overflow-x-auto rounded-lg border border-zinc-200" x-show="! editForm">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                        <th class="px-4 py-2.5">Product</th>
                                        <th class="px-4 py-2.5 text-right">Qty</th>
                                        <th class="whitespace-nowrap px-4 py-2.5 text-right">Cost Price</th>
                                        <th class="whitespace-nowrap px-4 py-2.5 text-right">Selling Price</th>
                                        <th class="whitespace-nowrap px-4 py-2.5 text-right">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    <template x-for="item in grn.items" x-bind:key="item.id">
                                        <tr class="align-top hover:bg-zinc-50">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-ink" x-text="item.product_name"></div>
                                                <div class="text-xs text-zinc-500" x-text="item.sku"></div>
                                                <div class="mt-1 text-xs text-zinc-500" x-show="item.serials.length > 0" x-text="`Serials: ${item.serials.join(', ')}`"></div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="item.qty"></td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(item.cost_price)"></td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                                                <span x-show="item.selling_price !== null" x-text="money(item.selling_price)"></span>
                                                <span x-show="item.selling_price === null" class="text-xs text-zinc-400">Product price</span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(item.line_total)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-zinc-200 bg-zinc-50">
                                        <td colspan="4" class="px-4 py-2.5 text-right text-xs uppercase tracking-wider text-zinc-500">Total Cost</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-ink" x-text="money(grn.total_cost)"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </x-modal>
    </div>
</x-layouts.app>
