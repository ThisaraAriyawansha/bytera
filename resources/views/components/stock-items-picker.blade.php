@props([
    'products' => [],
])

{{--
    "Add Item" card + items table for pages that take stock from one location (Stock Transfer, Stock Out).
    Must sit inside an Alpine `stockItemsForm()` scope.
--}}
@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<div class="nexora-card p-5">
    <h2 class="mb-4 font-prata text-base text-ink">Add Item</h2>

    <div class="grid gap-3 md:grid-cols-12">
        <div class="md:col-span-7">
            <span class="{{ $labelClasses }}">Product *</span>
            <x-searchable-select :options="$products" placeholder="Select a product" no-results="No products with stock" x-model="draft.product_id" />
            <p class="mt-1 text-xs text-zinc-500" x-show="draftProduct" x-cloak>
                <span x-text="available(draftProduct)"></span> available in <span x-text="locationLabel"></span>
            </p>
            <p x-show="draftErrors.product_id" x-text="draftErrors.product_id" x-cloak class="{{ $errorClasses }}"></p>
        </div>
        <div class="md:col-span-3" x-show="! draftProduct?.track_serial">
            <label for="draft-qty" class="{{ $labelClasses }}">Qty *</label>
            <input id="draft-qty" type="number" min="1" step="1" class="nexora-input" x-model="draft.qty" x-on:keydown.enter.prevent="addItem()">
            <p x-show="draftErrors.qty" x-text="draftErrors.qty" x-cloak class="{{ $errorClasses }}"></p>
        </div>
        <div class="md:col-span-3" x-show="draftProduct?.track_serial" x-cloak>
            <span class="{{ $labelClasses }}">Serials picked</span>
            <p class="py-2 text-sm text-ink"><span x-text="draft.unit_ids.length"></span> selected</p>
        </div>
        <div class="flex items-end md:col-span-2">
            <button type="button" class="nexora-btn nexora-btn-outline w-full justify-center" x-on:click="addItem()">
                <x-lucide-plus class="h-4 w-4" /> Add
            </button>
        </div>

        <template x-if="draftProduct?.track_serial">
            <div class="md:col-span-12">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-zinc-600">Pick serial numbers in <span x-text="locationLabel"></span></span>
                    <button type="button" class="text-xs font-medium text-brand hover:underline" x-show="pickableUnits.length > 0" x-on:click="toggleAllUnits()"
                            x-text="draft.unit_ids.length === pickableUnits.length ? 'Clear all' : 'Select all'"></button>
                </div>
                <p x-show="loadingUnits" class="py-3 text-sm text-zinc-400">Loading serial numbers…</p>
                <p x-show="! loadingUnits && pickableUnits.length === 0" class="py-3 text-sm text-zinc-400">
                    No serial numbers left in <span x-text="locationLabel"></span>.
                </p>
                <div x-show="! loadingUnits && pickableUnits.length > 0" class="grid max-h-56 gap-1 overflow-y-auto rounded-md border border-zinc-200 p-2 sm:grid-cols-2 lg:grid-cols-4">
                    <template x-for="unit in pickableUnits" x-bind:key="unit.id">
                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-zinc-50">
                            <input type="checkbox" class="rounded border-zinc-300 text-brand focus:ring-brand" x-bind:value="unit.id" x-model.number="draft.unit_ids">
                            <span class="truncate font-mono text-xs text-ink" x-text="unit.serial_number"></span>
                        </label>
                    </template>
                </div>
                <p x-show="draftErrors.unit_ids" x-text="draftErrors.unit_ids" class="{{ $errorClasses }}"></p>
            </div>
        </template>
    </div>
</div>

<div class="nexora-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                    <th class="px-5 py-3">Product</th>
                    <th class="px-5 py-3 text-right">Qty</th>
                    <th class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                <tr x-show="items.length === 0">
                    <td colspan="3" class="px-5 py-10 text-center text-sm text-zinc-400">No items yet. Add products above.</td>
                </tr>
                <template x-for="(item, index) in items" x-bind:key="item.key">
                    <tr class="align-top hover:bg-zinc-50">
                        <td class="px-5 py-3">
                            <div class="font-medium text-ink" x-text="item.name"></div>
                            <div class="text-xs text-zinc-500" x-text="item.sku"></div>
                            <div class="mt-1 text-xs text-zinc-500" x-show="item.track_serial" x-text="`Serials: ${item.units.map((unit) => unit.serial_number).join(', ')}`"></div>
                            <template x-for="message in itemErrors(index)">
                                <p class="{{ $errorClasses }}" x-text="message"></p>
                            </template>
                        </td>
                        <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums" x-text="item.qty"></td>
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
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
