@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="stockItemsForm(@js([
             'products' => $products,
             'storeUrl' => route('stock-transfer.store'),
             'unitsUrl' => route('api.products.units', '__PRODUCT__'),
             'location' => 'stores',
             'header' => ['note' => ''],
         ]))">
        <x-page-header title="New Stock Transfer" subtitle="Move stock from Stores to the Showroom so the POS can sell it.">
            <x-slot:actions>
                <a href="{{ route('stock-transfer.index') }}" class="nexora-btn nexora-btn-outline">
                    <x-lucide-arrow-left class="h-4 w-4" /> Back to Transfers
                </a>
            </x-slot:actions>
        </x-page-header>

        <form class="space-y-6" x-on:submit.prevent="save()">
            <p x-show="generalError" x-text="generalError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>

            <div class="nexora-card p-5">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <span class="{{ $labelClasses }}">Direction</span>
                        <p class="flex items-center gap-2 py-2 text-sm font-medium text-ink">
                            Stores <x-lucide-arrow-right class="h-4 w-4 text-brand" /> Showroom
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <label for="transfer-note" class="{{ $labelClasses }}">Note</label>
                        <input id="transfer-note" type="text" class="nexora-input" maxlength="500" placeholder="e.g. Restocking the display shelf" x-model="header.note">
                        <p x-show="errors.note" x-text="errors.note" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
            </div>

            @if ($products === [])
                <x-empty-state icon="package" title="Nothing in Stores" message="There is no stock in Stores to transfer. Receive goods with a GRN first." />
            @else
                <x-stock-items-picker :products="$products" />
            @endif

            <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <a href="{{ route('stock-transfer.index') }}" class="nexora-btn nexora-btn-outline justify-center">Cancel</a>
                <button type="submit" class="nexora-btn nexora-btn-primary justify-center disabled:opacity-60" x-bind:disabled="saving || items.length === 0">
                    <x-lucide-arrow-left-right class="h-4 w-4" />
                    <span x-text="saving ? 'Saving…' : `Transfer ${totalUnits} ${totalUnits === 1 ? 'unit' : 'units'} to Showroom`">Transfer to Showroom</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
