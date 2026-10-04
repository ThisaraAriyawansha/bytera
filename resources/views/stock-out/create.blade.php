@php
    use App\Models\StockOut;
    use App\Services\StockService;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="stockItemsForm(@js([
             'products' => $products,
             'storeUrl' => route('stock-out.store'),
             'unitsUrl' => route('api.products.units', '__PRODUCT__'),
             'location' => 'showroom',
             'header' => ['recipient' => '', 'reason' => 'job', 'reason_detail' => '', 'job_id' => null, 'note' => ''],
         ]))">
        <x-page-header title="New Stock Out" subtitle="Issue stock that leaves without a POS sale.">
            <x-slot:actions>
                <a href="{{ route('stock-out.index') }}" class="nexora-btn nexora-btn-outline">
                    <x-lucide-arrow-left class="h-4 w-4" /> Back to Stock Out
                </a>
            </x-slot:actions>
        </x-page-header>

        <form class="space-y-6" x-on:submit.prevent="save()">
            <p x-show="generalError" x-text="generalError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>

            <div class="nexora-card space-y-4 p-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <span class="{{ $labelClasses }}">Issue From</span>
                        <div class="inline-flex w-full rounded-md bg-zinc-100 p-1" role="radiogroup" aria-label="Issue From">
                            @foreach (array_reverse(StockService::LOCATIONS, true) as $value => $label)
                                <button type="button" role="radio" class="flex-1 rounded px-3 py-1.5 text-sm transition-colors"
                                        x-bind:aria-checked="location === @js($value)"
                                        x-bind:class="location === @js($value) ? 'bg-white font-medium text-ink shadow-sm' : 'text-zinc-500 hover:text-ink'"
                                        x-on:click="location = @js($value)">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-zinc-500" x-show="items.length > 0" x-cloak>Changing this clears the item list.</p>
                    </div>
                    <div>
                        <label for="stock-out-recipient" class="{{ $labelClasses }}">Issued To *</label>
                        <input id="stock-out-recipient" type="text" class="nexora-input" maxlength="255" placeholder="Customer, technician, or department" x-model="header.recipient">
                        <p x-show="errors.recipient" x-text="errors.recipient" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <span class="{{ $labelClasses }}">Reason *</span>
                        <div class="inline-flex w-full rounded-md bg-zinc-100 p-1" role="radiogroup" aria-label="Reason">
                            @foreach (StockOut::REASONS as $value => $label)
                                <button type="button" role="radio" class="flex-1 rounded px-3 py-1.5 text-sm transition-colors"
                                        x-bind:aria-checked="header.reason === @js($value)"
                                        x-bind:class="header.reason === @js($value) ? 'bg-white font-medium text-ink shadow-sm' : 'text-zinc-500 hover:text-ink'"
                                        x-on:click="header.reason = @js($value)">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <p x-show="errors.reason" x-text="errors.reason" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div x-show="header.reason === 'job'">
                        <span class="{{ $labelClasses }}">Job *</span>
                        <x-searchable-select :search-url="route('api.jobs.search')" placeholder="Select a job" no-results="No jobs found" x-model="header.job_id" />
                        <p x-show="errors.job_id" x-text="errors.job_id" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="stock-out-detail" class="{{ $labelClasses }}" x-text="header.reason === 'other' ? 'Detail *' : 'Detail'">Detail</label>
                        <input id="stock-out-detail" type="text" class="nexora-input" maxlength="255" placeholder="e.g. Replaced SSD under warranty" x-model="header.reason_detail">
                        <p x-show="errors.reason_detail" x-text="errors.reason_detail" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="stock-out-note" class="{{ $labelClasses }}">Note</label>
                        <input id="stock-out-note" type="text" class="nexora-input" maxlength="500" x-model="header.note">
                        <p x-show="errors.note" x-text="errors.note" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
            </div>

            @if ($products === [])
                <x-empty-state icon="package" title="No stock" message="There is no stock in Stores or Showroom to issue." />
            @else
                <x-stock-items-picker :products="$products" />
            @endif

            <div class="flex flex-col-reverse justify-end gap-2 sm:flex-row">
                <a href="{{ route('stock-out.index') }}" class="nexora-btn nexora-btn-outline justify-center">Cancel</a>
                <button type="submit" class="nexora-btn nexora-btn-primary justify-center disabled:opacity-60" x-bind:disabled="saving || items.length === 0">
                    <x-lucide-package-minus class="h-4 w-4" />
                    <span x-text="saving ? 'Saving…' : `Issue ${totalUnits} ${totalUnits === 1 ? 'unit' : 'units'} from ${locationLabel}`">Issue Stock</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
