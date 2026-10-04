@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="recordForm(@js([
             'modal' => 'brand-form',
             'storeUrl' => route('brands.store'),
             'blank' => ['name' => '', 'description' => ''],
         ]))">
        <x-page-header title="Brands" subtitle="Product brands.">
            <x-slot:actions>
                <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="openAdd()">
                    <x-lucide-plus class="h-4 w-4" /> Add Brand
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-flash />

        @if ($brands->isEmpty())
            <x-empty-state icon="book-marked" title="No brands yet" message="Add the brands you sell, then pick them when you add products." />
        @else
            <div class="nexora-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-5 py-3">Brand</th>
                                <th class="px-5 py-3">Description</th>
                                <th class="px-5 py-3 text-right">Products</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($brands as $brand)
                                <tr class="hover:bg-zinc-50">
                                    <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $brand->name }}</td>
                                    <td class="px-5 py-3 text-zinc-600">{{ $brand->description ?: '—' }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-zinc-600">{{ number_format($brand->products_count) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                x-on:click="openEdit(@js([
                                                    'name' => $brand->name,
                                                    'description' => $brand->description,
                                                    'updateUrl' => route('brands.update', $brand),
                                                ]))"
                                                aria-label="Edit {{ $brand->name }}">
                                            <x-lucide-pencil class="h-4 w-4" />
                                        </button>
                                        @if ($canDelete)
                                            <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                    x-on:click="$dispatch('open-modal', @js([
                                                        'name' => 'delete-brand',
                                                        'message' => $brand->products_count > 0
                                                            ? "\"{$brand->name}\" is used by {$brand->products_count} ".str('product')->plural($brand->products_count).' and can\'t be deleted until they are moved to another brand.'
                                                            : "Delete the brand \"{$brand->name}\"? This can't be undone.",
                                                        'action' => route('brands.destroy', $brand),
                                                    ]))"
                                                    aria-label="Delete {{ $brand->name }}">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-pagination :paginator="$brands" />
            </div>
        @endif

        <x-modal name="brand-form">
            <x-slot:title><span x-text="isEditing ? 'Edit Brand' : 'Add Brand'">Add Brand</span></x-slot:title>

            <form id="brand-form" class="space-y-4" x-on:submit.prevent="save()">
                <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div>
                    <label for="brand-name" class="{{ $labelClasses }}">Brand name *</label>
                    <input id="brand-name" type="text" class="nexora-input" placeholder="e.g. HP" maxlength="100" x-model="form.name" required autofocus>
                    <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="brand-description" class="{{ $labelClasses }}">Description</label>
                    <textarea id="brand-description" rows="3" class="nexora-input" maxlength="500" placeholder="Optional" x-model="form.description"></textarea>
                    <p x-show="errors.description" x-text="errors.description" x-cloak class="{{ $errorClasses }}"></p>
                </div>
            </form>

            <x-slot:footer>
                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'brand-form')">Cancel</button>
                <button type="submit" form="brand-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                    <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Brand'">Save</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </button>
            </x-slot:footer>
        </x-modal>

        @if ($canDelete)
            <x-confirm-dialog name="delete-brand" title="Delete brand?" confirm-label="Delete" />
        @endif
    </div>
</x-layouts.app>
