@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="grnForm(@js(['products' => $products, 'storeUrl' => route('grn.store')]))">
        <x-page-header title="New GRN" subtitle="Receive goods from a supplier into stock.">
            <x-slot:actions>
                <a href="{{ route('grn.index') }}" class="nexora-btn nexora-btn-outline">
                    <x-lucide-arrow-left class="h-4 w-4" /> Back to GRNs
                </a>
            </x-slot:actions>
        </x-page-header>

        <form class="space-y-6" x-on:submit.prevent="save()">
            <p x-show="generalError" x-text="generalError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>

            {{-- Receipt details --}}
            <div class="nexora-card p-5">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <span class="{{ $labelClasses }}">Supplier</span>
                        <x-searchable-select :options="$supplierOptions" empty-label="No supplier" placeholder="No supplier"
                                             no-results="No suppliers found" x-model="supplierId" />
                        <p x-show="errors.supplier_id" x-text="errors.supplier_id" x-cloak class="{{ $errorClasses }}"></p>
                        <p class="mt-1 text-xs text-zinc-500" x-show="supplierId">The GRN total is added to what we owe this supplier.</p>
                    </div>
                    <div>
                        <span class="{{ $labelClasses }}">Location</span>
                        <div class="inline-flex w-full rounded-md bg-zinc-100 p-1" role="radiogroup" aria-label="Location">
                            @foreach (\App\Services\StockService::LOCATIONS as $value => $label)
                                <button type="button" role="radio" class="flex-1 rounded px-3 py-1.5 text-sm transition-colors"
                                        x-bind:aria-checked="location === @js($value)"
                                        x-bind:class="location === @js($value) ? 'bg-white font-medium text-ink shadow-sm' : 'text-zinc-500 hover:text-ink'"
                                        x-on:click="location = @js($value)">
                                    {{ $label }}{{ $value === 'stores' ? ' (default)' : '' }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label for="grn-note" class="{{ $labelClasses }}">Note</label>
                        <input id="grn-note" type="text" class="nexora-input" maxlength="500" placeholder="e.g. supplier invoice no" x-model="note">
                        <p x-show="errors.note" x-text="errors.note" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
            </div>

            {{-- Add Item --}}
            <div class="nexora-card p-5">
                <h2 class="mb-4 font-prata text-base text-ink">Add Item</h2>

                <div class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-5">
                        <span class="{{ $labelClasses }}">Product *</span>
                        <x-searchable-select :options="$products" placeholder="Select a product" no-results="No products found" x-model="draft.product_id" />
                        <p x-show="draftErrors.product_id" x-text="draftErrors.product_id" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="draft-cost" class="{{ $labelClasses }}">Cost price *</label>
                        <input id="draft-cost" type="number" min="0.01" step="0.01" class="nexora-input" x-model="draft.cost_price" x-on:keydown.enter.prevent="addItem()">
                        <p x-show="draftErrors.cost_price" x-text="draftErrors.cost_price" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="draft-price" class="{{ $labelClasses }}">Selling price</label>
                        <input id="draft-price" type="number" min="0" step="0.01" class="nexora-input" placeholder="Use product price" x-model="draft.selling_price" x-on:keydown.enter.prevent="addItem()">
                        <p class="mt-1 text-xs text-zinc-400" x-show="draftProduct && draft.selling_price === ''">Product price <span x-text="money(draftProduct?.selling_price)"></span></p>
                        <p x-show="draftErrors.selling_price" x-text="draftErrors.selling_price" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="draft-qty" class="{{ $labelClasses }}" x-text="draftProduct?.track_serial ? 'Units (one serial each) *' : 'Qty *'">Qty *</label>
                        <input id="draft-qty" type="number" min="1" step="1" class="nexora-input" x-model="draft.qty"
                               x-on:input="draftProduct?.track_serial && syncDraftSerials()" x-on:keydown.enter.prevent="addItem()">
                        <p x-show="draftErrors.qty" x-text="draftErrors.qty" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div class="md:col-span-1">
                        <span class="mb-1 hidden text-xs md:invisible md:block" aria-hidden="true">&nbsp;</span>
                        <button type="button" class="nexora-btn nexora-btn-outline w-full justify-center" x-on:click="addItem()">
                            <x-lucide-plus class="h-4 w-4" /> Add
                        </button>
                    </div>

                    <template x-if="draftProduct?.track_serial && draft.serials.length > 0">
                        <div class="md:col-span-12">
                            <span class="{{ $labelClasses }}">Serial numbers</span>
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                <template x-for="(serial, index) in draft.serials" x-bind:key="index">
                                    <input type="text" class="nexora-input" maxlength="100"
                                           x-bind:placeholder="`Serial #${index + 1}`" x-bind:aria-label="`Serial number ${index + 1}`"
                                           x-model="draft.serials[index]">
                                </template>
                            </div>
                            <p x-show="draftErrors.serials" x-text="draftErrors.serials" class="{{ $errorClasses }}"></p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Items --}}
            <div class="nexora-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3 text-right">Qty</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Cost Price</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Selling Price</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Line Total</th>
                                <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <tr x-show="items.length === 0">
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-zinc-400">No items yet. Add the products you received above.</td>
                            </tr>
                            <template x-for="(item, index) in items" x-bind:key="item.key">
                                <tr class="align-top hover:bg-zinc-50">
                                    <td class="px-5 py-3">
                                        <div class="font-medium text-ink" x-text="item.name"></div>
                                        <div class="text-xs text-zinc-500" x-text="item.sku"></div>
                                        <div class="mt-1 text-xs text-zinc-500" x-show="item.track_serial" x-text="`Serials: ${item.serials.join(', ')}`"></div>
                                        <template x-for="message in itemErrors(index)">
                                            <p class="{{ $errorClasses }}" x-text="message"></p>
                                        </template>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums" x-text="item.qty"></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums" x-text="money(item.cost_price)"></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">
                                        <span x-show="item.selling_price !== null" x-text="money(item.selling_price)"></span>
                                        <span x-show="item.selling_price === null" class="text-xs text-zinc-400">Product price</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right font-medium tabular-nums" x-text="money(lineTotal(item))"></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                x-on:click="removeItem(index)" x-bind:aria-label="`Remove ${item.name}`" title="Remove">
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot x-show="items.length > 0" x-cloak>
                            <tr class="border-t border-zinc-200 bg-zinc-50">
                                <td class="px-5 py-3 font-medium text-ink">
                                    <span x-text="items.length"></span> <span x-text="items.length === 1 ? 'item' : 'items'"></span>
                                </td>
                                <td class="px-5 py-3 text-right font-medium tabular-nums" x-text="totalUnits"></td>
                                <td colspan="2" class="px-5 py-3 text-right text-xs uppercase tracking-wider text-zinc-500">Total Cost</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right font-prata text-lg text-ink" x-text="money(total)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <a href="{{ route('grn.index') }}" class="nexora-btn nexora-btn-outline justify-center">Cancel</a>
                <button type="submit" class="nexora-btn nexora-btn-primary justify-center disabled:opacity-60" x-bind:disabled="saving || items.length === 0">
                    <x-lucide-save class="h-4 w-4" />
                    <span x-text="saving ? 'Saving…' : `Save GRN — ${money(total)}`">Save GRN</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
