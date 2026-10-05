@php
    use App\Models\Quotation;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $sectionClasses = 'mb-2 font-prata text-sm text-ink';
    $total = $quotations->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="quotationView()" x-on:quotation-saved.window="show($event.detail.url, $event.detail.message)">
        <div x-data="quotationForm(@js($quotationConfig))">
            <x-page-header title="Quotations" :subtitle="'Price quotations for customers · '.number_format($total).' '.str('quotation')->plural($total).' in this period'">
                <x-slot:actions>
                    <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="open()">
                        <x-lucide-plus class="h-4 w-4" /> New Quotation
                    </button>
                </x-slot:actions>
            </x-page-header>

            {{-- ═══ New Quotation ═══ --}}
            <x-modal name="quotation-form" title="New Quotation" max-width="4xl">
                <form class="space-y-5" x-on:submit.prevent="save()">
                    <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    {{-- Customer --}}
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="quotation-customer" class="{{ $labelClasses }}">Customer name</label>
                            <input id="quotation-customer" type="text" class="nexora-input" maxlength="100" placeholder="Walk-in Customer" autofocus x-model="form.customer_name">
                            <p x-show="errors.customer_name" x-text="errors.customer_name" class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="quotation-phone" class="{{ $labelClasses }}">Phone</label>
                            <input id="quotation-phone" type="tel" class="nexora-input" maxlength="30" x-model="form.customer_phone">
                            <p x-show="errors.customer_phone" x-text="errors.customer_phone" class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="quotation-address" class="{{ $labelClasses }}">Address</label>
                            <input id="quotation-address" type="text" class="nexora-input" maxlength="500" x-model="form.customer_address">
                            <p x-show="errors.customer_address" x-text="errors.customer_address" class="{{ $errorClasses }}"></p>
                        </div>
                    </div>

                    {{-- Add Item --}}
                    <div>
                        <span class="{{ $labelClasses }}">Add Item</span>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <div class="relative flex-1" x-on:click.outside="productSearch = ''">
                                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input type="search" class="nexora-input pl-9" autocomplete="off" aria-label="Search products"
                                       placeholder="Search products by name or SKU…" x-model="productSearch"
                                       x-on:keydown.enter.prevent="productResults.length > 0 ? addProduct(productResults[0]) : (productSearch.trim() && addCustomItem())">
                                <div x-show="productSearch.trim() !== ''" x-cloak
                                     class="absolute inset-x-0 top-full z-10 mt-1 max-h-64 overflow-y-auto rounded-md border border-zinc-200 bg-white shadow-lg">
                                    <template x-for="product in productResults" :key="product.id">
                                        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50"
                                                x-on:click="addProduct(product)">
                                            <span class="min-w-0">
                                                <span class="block truncate text-ink" x-text="product.name"></span>
                                                <span class="block text-xs text-zinc-500" x-text="product.sku"></span>
                                            </span>
                                            <span class="whitespace-nowrap tabular-nums text-zinc-600" x-text="money(product.price)"></span>
                                        </button>
                                    </template>
                                    <button type="button" class="flex w-full items-center gap-2 border-t border-zinc-100 px-3 py-2 text-left text-sm text-brand hover:bg-brand-light"
                                            x-on:click="addCustomItem()">
                                        <x-lucide-plus class="h-4 w-4" /> <span x-text="`Add &quot;${productSearch.trim()}&quot; as a custom item`"></span>
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="nexora-btn nexora-btn-outline justify-center" x-on:click="addCustomItem()">
                                <x-lucide-plus class="h-4 w-4" /> Custom item
                            </button>
                        </div>
                        <p x-show="errors.items" x-text="errors.items" class="{{ $errorClasses }}"></p>
                    </div>

                    {{-- Items --}}
                    <div class="overflow-x-auto rounded-lg border border-zinc-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    <th class="px-3 py-2.5">Item</th>
                                    <th class="w-20 px-3 py-2.5">Qty</th>
                                    <th class="w-32 whitespace-nowrap px-3 py-2.5">Unit Price</th>
                                    <th class="w-28 px-3 py-2.5">Discount</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right">Total</th>
                                    <th class="relative w-10 px-3 py-2.5"><span class="sr-only">Remove</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <template x-for="(item, index) in form.items" :key="item.key">
                                    <tr class="align-top">
                                        <td class="min-w-[12rem] px-3 py-2">
                                            <template x-if="item.product_id">
                                                <div>
                                                    <div class="font-medium text-ink" x-text="item.product_name"></div>
                                                    <div class="text-xs text-zinc-500" x-text="item.sku"></div>
                                                </div>
                                            </template>
                                            <template x-if="! item.product_id">
                                                <input type="text" class="nexora-input" maxlength="150" required data-item-name
                                                       placeholder="Item name" aria-label="Item name" x-model="item.product_name">
                                            </template>
                                            <p x-show="itemError(index)" x-text="itemError(index)" class="{{ $errorClasses }}"></p>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" min="1" step="1" class="nexora-input" required aria-label="Quantity" x-model="item.qty">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" min="0" step="0.01" class="nexora-input" required aria-label="Unit price" x-model="item.unit_price">
                                        </td>
                                        <td class="px-3 py-2">
                                            <input type="number" min="0" step="0.01" class="nexora-input" placeholder="0" aria-label="Discount per unit" x-model="item.discount">
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 pt-4 text-right tabular-nums" x-text="money(lineTotalCents(item) / 100)"></td>
                                        <td class="px-3 py-2 pt-3 text-right">
                                            <button type="button" class="rounded p-1.5 text-zinc-400 hover:bg-zinc-100 hover:text-brand" x-on:click="removeItem(index)" aria-label="Remove item">
                                                <x-lucide-x class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="form.items.length === 0">
                                    <td colspan="6" class="px-3 py-8 text-center text-sm text-zinc-400">Search for a product or add a custom item.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Note / valid until / totals --}}
                    <div class="grid gap-4 sm:grid-cols-[1fr_16rem]">
                        <div class="space-y-3">
                            <div>
                                <label for="quotation-note" class="{{ $labelClasses }}">Note / Terms</label>
                                <textarea id="quotation-note" rows="3" class="nexora-input" maxlength="2000"
                                          placeholder="e.g. Prices include installation. 50% advance on confirmation." x-model="form.note"></textarea>
                                <p x-show="errors.note" x-text="errors.note" class="{{ $errorClasses }}"></p>
                            </div>
                            <div class="sm:max-w-[12rem]">
                                <label for="quotation-valid-until" class="{{ $labelClasses }}">Valid until *</label>
                                <input id="quotation-valid-until" type="date" class="nexora-input" required x-model="form.valid_until">
                                <p x-show="errors.valid_until" x-text="errors.valid_until" class="{{ $errorClasses }}"></p>
                            </div>
                        </div>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-zinc-500">Subtotal</dt><dd class="tabular-nums" x-text="money(subtotalCents / 100)"></dd></div>
                            <div>
                                <label for="quotation-discount" class="{{ $labelClasses }}">Discount</label>
                                <input id="quotation-discount" type="number" min="0" step="0.01" class="nexora-input" placeholder="0" x-model="form.discount_amount">
                                <p x-show="errors.discount_amount" x-text="errors.discount_amount" class="{{ $errorClasses }}"></p>
                            </div>
                            <div class="flex items-baseline justify-between border-t border-zinc-200 pt-2">
                                <dt class="font-medium">Total</dt>
                                <dd class="font-prata text-xl tabular-nums" x-bind:class="totalCents < 0 ? 'text-brand' : 'text-ink'" x-text="money(totalCents / 100)"></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4">
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'quotation-form')">Cancel</button>
                        <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving || form.items.length === 0">
                            <span x-text="saving ? 'Saving…' : 'Save Quotation'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        </div>

        <x-flash />

        <form method="GET" action="{{ route('quotations.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search quotation no., customer or phone…" :value="$search" aria-label="Search quotations" class="lg:max-w-sm lg:flex-1" />
            <label class="lg:w-44">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Status</span>
                <select name="status" class="nexora-input" onchange="this.form.requestSubmit()">
                    <option value="">All statuses</option>
                    @foreach (Quotation::STATUSES as $key => $meta)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Quotation</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Issued</th>
                            <th class="whitespace-nowrap px-5 py-3">Valid Until</th>
                            <th class="px-5 py-3 text-right">Total</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($quotations as $quotation)
                            @php $displayStatus = $quotation->displayStatus(); @endphp
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $quotation->quotation_no }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-ink">{{ $quotation->customer_name }}</div>
                                    @if (filled($quotation->customer_phone))
                                        <div class="text-xs text-zinc-500">{{ $quotation->customer_phone }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $quotation->created_at->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $quotation->valid_until->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-ink">{{ Money::format($quotation->total_amount) }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="Quotation::STATUSES[$displayStatus]['variant']">{{ Quotation::STATUSES[$displayStatus]['label'] }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="show(@js(route('quotations.show', $quotation)))"
                                            title="View" aria-label="View {{ $quotation->quotation_no }}">
                                        <x-lucide-eye class="h-4 w-4" />
                                    </button>
                                    @if ($canDelete)
                                        <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                x-on:click="$dispatch('open-modal', {
                                                    name: 'quotation-delete',
                                                    message: @js("Delete {$quotation->quotation_no} for {$quotation->customer_name}? This can't be undone."),
                                                    action: @js(route('quotations.destroy', $quotation)),
                                                })"
                                                title="Delete" aria-label="Delete {{ $quotation->quotation_no }}">
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No quotations match "'.$search.'" in this period.' : 'No quotations in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$quotations" />
        </div>

        {{-- ═══ View ═══ --}}
        <x-modal name="quotation-view" max-width="4xl" x-model="viewOpen">
            <x-slot:title>
                <span class="flex flex-wrap items-center gap-2">
                    <span x-text="quotation?.quotation_no ?? 'Quotation'">Quotation</span>
                    <span x-show="quotation" x-cloak class="badge font-poppins" x-bind:class="`badge-${quotation?.status_variant}`" x-text="quotation?.status_label"></span>
                </span>
            </x-slot:title>

            <div class="space-y-5">
                <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                <p x-show="notice" x-text="notice" x-cloak class="no-print rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                <p x-show="viewErrors.form" x-text="viewErrors.form" x-cloak class="no-print rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                <template x-if="quotation">
                    <div class="space-y-5">
                        {{-- Actions --}}
                        <div class="no-print flex flex-wrap gap-2">
                            <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="printQuotation()">
                                <x-lucide-printer class="h-4 w-4" /> Print A4
                            </button>
                            <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-bind:disabled="pdfBusy" x-on:click="downloadQuotation()">
                                <x-lucide-download class="h-4 w-4" /> <span x-text="pdfBusy ? 'Preparing…' : 'Download'"></span>
                            </button>
                            <template x-if="quotation.status !== 'converted'">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-show="quotation.status !== 'accepted'"
                                            x-bind:disabled="statusSaving !== ''" x-on:click="setStatus('accepted')">
                                        <x-lucide-circle-check class="h-4 w-4 text-green-600" /> <span x-text="statusSaving === 'accepted' ? 'Saving…' : 'Mark Accepted'"></span>
                                    </button>
                                    <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-show="quotation.status !== 'rejected'"
                                            x-bind:disabled="statusSaving !== ''" x-on:click="setStatus('rejected')">
                                        <x-lucide-circle-x class="h-4 w-4 text-red-600" /> <span x-text="statusSaving === 'rejected' ? 'Saving…' : 'Mark Rejected'"></span>
                                    </button>
                                </div>
                            </template>
                            @if ($canDelete)
                                <button type="button" class="nexora-btn nexora-btn-danger sm:ml-auto" x-on:click="confirmDelete()">
                                    <x-lucide-trash-2 class="h-4 w-4" /> Delete
                                </button>
                            @endif
                        </div>

                        {{-- Details --}}
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                <h4 class="{{ $sectionClasses }}">Customer</h4>
                                <p class="font-medium text-ink" x-text="quotation.customer_name"></p>
                                <p class="text-zinc-600" x-show="quotation.customer_phone" x-text="quotation.customer_phone"></p>
                                <p class="whitespace-pre-line text-zinc-600" x-show="quotation.customer_address" x-text="quotation.customer_address"></p>
                            </div>
                            <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                <h4 class="{{ $sectionClasses }}">Quotation</h4>
                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                                    <dt class="text-zinc-500">Issued</dt><dd class="text-ink" x-text="quotation.date"></dd>
                                    <dt class="text-zinc-500">Valid until</dt><dd class="text-ink" x-text="quotation.valid_until"></dd>
                                    <dt class="text-zinc-500">Prepared by</dt><dd class="text-ink" x-text="quotation.prepared_by_name"></dd>
                                    <dt class="text-zinc-500">Total</dt><dd class="font-prata text-ink" x-text="money(quotation.total_amount)"></dd>
                                </dl>
                            </div>
                        </div>

                        {{-- A4 quotation (printed / downloaded) --}}
                        <div>
                            <h4 class="no-print {{ $sectionClasses }}">Quotation (A4)</h4>
                            <div class="overflow-x-auto rounded-md border border-zinc-200 bg-zinc-100 p-2">
                                <div class="mx-auto w-[210mm] shadow-sm" x-html="printHtml"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </x-modal>

        @if ($canDelete)
            <x-confirm-dialog name="quotation-delete" title="Delete quotation?" confirm-label="Delete" />
        @endif
    </div>
</x-layouts.app>
