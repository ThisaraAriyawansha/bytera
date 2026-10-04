@php
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $iconButtonClasses = 'rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-zinc-500';
    $hasFilters = $search !== '' || array_filter($filters) !== [];
    $sectionTitleClasses = 'mb-3 font-prata text-sm text-ink';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="productForm(@js([
             'modal' => 'product-form',
             'storeUrl' => route('products.store'),
             'mainCategories' => $mainCategories,
         ]))">
        <div x-data="productStock()" x-on:confirmed.window="handleConfirmed($event.detail)">
            <x-page-header title="Products" subtitle="Product catalogue, stock batches and serial numbers.">
                @if ($canEdit)
                    <x-slot:actions>
                        <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="openAdd()">
                            <x-lucide-plus class="h-4 w-4" /> Add Product
                        </button>
                    </x-slot:actions>
                @endif
            </x-page-header>

            <x-flash />

            <form method="GET" action="{{ route('products.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center">
                <x-search-input placeholder="Search by name, SKU or barcode…" :value="$search" aria-label="Search products" class="lg:max-w-sm lg:flex-1" />
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:flex">
                    <select name="brand" class="nexora-input lg:w-44" aria-label="Filter by brand" onchange="this.form.submit()">
                        <option value="">All brands</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected($filters['brand'] === $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select>
                    <select name="category" class="nexora-input lg:w-44" aria-label="Filter by category" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        @foreach ($mainCategories as $mainCategory)
                            <option value="{{ $mainCategory->id }}" @selected($filters['category'] === $mainCategory->id)>{{ $mainCategory->name }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="nexora-input lg:w-36" aria-label="Filter by status" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>
                @if ($hasFilters)
                    <a href="{{ route('products.index') }}" class="nexora-btn nexora-btn-ghost justify-center">
                        <x-lucide-x class="h-4 w-4" /> Clear
                    </a>
                @endif
            </form>

            <div class="nexora-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-5 py-3">Product</th>
                                <th class="px-5 py-3">Brand</th>
                                <th class="px-5 py-3">Category</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Selling Price</th>
                                <th class="px-5 py-3">Warranty</th>
                                <th class="px-5 py-3">Stock</th>
                                <th class="px-5 py-3 text-center">Batches</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($products as $product)
                                @php($isLow = $product->isLowOnStock())
                                <tr class="hover:bg-zinc-50">
                                    <td class="min-w-[12rem] px-5 py-3">
                                        <div class="flex items-center gap-1.5 font-medium text-ink">
                                            {{ $product->name }}
                                            @if ($product->track_serial)
                                                <span class="badge badge-info" title="Serial-tracked">S/N</span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            {{ $product->sku }}@if ($product->barcode) · {{ $product->barcode }}@endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $product->brand?->name ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-zinc-600">
                                        {{ $product->mainCategory?->name ?? '—' }}
                                        <span class="text-zinc-400">›</span>
                                        {{ $product->subCategory?->name ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($product->selling_price) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-zinc-600">
                                        {{ $product->warranty_months > 0 ? $product->warranty_months.' '.str('month')->plural($product->warranty_months) : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span @class(['font-semibold tabular-nums', 'text-brand' => $isLow, 'text-ink' => ! $isLow])>{{ number_format($product->total_stock) }}</span>
                                            @if ($isLow)
                                                <x-badge :variant="$product->total_stock === 0 ? 'danger' : 'warning'">{{ $product->total_stock === 0 ? 'Out of stock' : 'Low' }}</x-badge>
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500">Stores {{ number_format($product->stores_stock) }} · Showroom {{ number_format($product->showroom_stock) }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-center tabular-nums">
                                        <span class="text-ink">{{ $product->active_batches_count }}</span>
                                        @if ($product->batches_count > $product->active_batches_count)
                                            <span class="text-xs text-zinc-400">/ {{ $product->batches_count }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-badge :variant="$product->active ? 'success' : 'default'">{{ $product->active ? 'Active' : 'Inactive' }}</x-badge>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <button type="button" class="{{ $iconButtonClasses }}"
                                                x-on:click="openBatches(@js(route('products.batches.index', $product)))"
                                                title="Stock batches" aria-label="Stock batches of {{ $product->name }}">
                                            <x-lucide-layers class="h-4 w-4" />
                                        </button>
                                        @if ($canEdit)
                                            <button type="button" class="{{ $iconButtonClasses }}"
                                                    x-on:click="openEdit(@js([
                                                        'name' => $product->name,
                                                        'brand_id' => $product->brand_id,
                                                        'sku' => $product->sku,
                                                        'barcode' => $product->barcode ?? '',
                                                        'main_category_id' => $product->main_category_id,
                                                        'sub_category_id' => $product->sub_category_id,
                                                        'description' => $product->description ?? '',
                                                        'selling_price' => (float) $product->selling_price,
                                                        'low_stock_alert' => $product->low_stock_alert,
                                                        'warranty_months' => $product->warranty_months,
                                                        'track_serial' => $product->track_serial,
                                                        'active' => $product->active,
                                                        'hasBatches' => $product->batches_count > 0,
                                                        'updateUrl' => route('products.update', $product),
                                                    ]))"
                                                    title="Edit" aria-label="Edit {{ $product->name }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if ($canDelete)
                                            <button type="button" class="{{ $iconButtonClasses }}"
                                                    x-on:click="$dispatch('open-modal', @js([
                                                        'name' => 'delete-product',
                                                        'message' => "Delete \"{$product->name}\" with all its batches and serial numbers? This can't be undone.",
                                                        'action' => route('products.destroy', $product),
                                                    ]))"
                                                    title="Delete" aria-label="Delete {{ $product->name }}">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-12 text-center text-sm text-zinc-400">
                                        {{ $hasFilters ? 'No products match your search.' : 'No products yet.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-pagination :paginator="$products" />
            </div>

            {{-- Add / Edit Product --}}
            @if ($canEdit)
                <x-modal name="product-form" max-width="2xl">
                    <x-slot:title><span x-text="isEditing ? 'Edit Product' : 'Add Product'">Add Product</span></x-slot:title>

                    <form id="product-form" class="space-y-6" x-on:submit.prevent="save()">
                        <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                        <section>
                            <h4 class="{{ $sectionTitleClasses }}">Product Details</h4>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label for="product-name" class="{{ $labelClasses }}">Name *</label>
                                    <input id="product-name" type="text" class="nexora-input" maxlength="150" x-model="form.name" required autofocus>
                                    <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div>
                                    <label for="product-brand" class="{{ $labelClasses }}">Brand *</label>
                                    <select id="product-brand" class="nexora-input" x-model="form.brand_id" required>
                                        <option value="">Select a brand</option>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="errors.brand_id" x-text="errors.brand_id" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div>
                                    <label for="product-sku" class="{{ $labelClasses }}">SKU *</label>
                                    <input id="product-sku" type="text" class="nexora-input" maxlength="60" x-model="form.sku" required>
                                    <p x-show="errors.sku" x-text="errors.sku" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="product-barcode" class="{{ $labelClasses }}">Barcode</label>
                                    <input id="product-barcode" type="text" class="nexora-input" maxlength="100" placeholder="Scan or type the product barcode" x-model="form.barcode">
                                    <p x-show="errors.barcode" x-text="errors.barcode" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div>
                                    <label for="product-main-category" class="{{ $labelClasses }}">Main Category *</label>
                                    <select id="product-main-category" class="nexora-input" x-model="form.main_category_id" x-on:change="mainCategoryChanged()" required>
                                        <option value="">Select a main category</option>
                                        @foreach ($mainCategories as $mainCategory)
                                            <option value="{{ $mainCategory->id }}">{{ $mainCategory->name }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="errors.main_category_id" x-text="errors.main_category_id" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div>
                                    <label for="product-sub-category" class="{{ $labelClasses }}">Sub Category *</label>
                                    <select id="product-sub-category" class="nexora-input disabled:bg-zinc-50" x-model="form.sub_category_id" x-bind:disabled="! form.main_category_id" required>
                                        <option value="">Select a subcategory</option>
                                        <template x-for="subCategory in subCategories" x-bind:key="subCategory.id">
                                            <option x-bind:value="subCategory.id" x-text="subCategory.name" x-bind:selected="String(subCategory.id) === String(form.sub_category_id)"></option>
                                        </template>
                                    </select>
                                    <p x-show="errors.sub_category_id" x-text="errors.sub_category_id" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="product-description" class="{{ $labelClasses }}">Description</label>
                                    <textarea id="product-description" rows="2" class="nexora-input" maxlength="1000" x-model="form.description"></textarea>
                                    <p x-show="errors.description" x-text="errors.description" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                            </div>
                        </section>

                        <section class="border-t border-zinc-200 pt-5">
                            <h4 class="{{ $sectionTitleClasses }}">Pricing &amp; Stock</h4>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="product-price" class="{{ $labelClasses }}">Selling price *</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rs.</span>
                                        <input id="product-price" type="number" min="0" step="0.01" class="nexora-input pl-10" x-model="form.selling_price" required>
                                    </div>
                                    <p x-show="errors.selling_price" x-text="errors.selling_price" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div class="hidden sm:block"></div>

                                <template x-if="! isEditing">
                                    <div class="contents">
                                        <div>
                                            <label for="product-initial-stock" class="{{ $labelClasses }}">Initial stock</label>
                                            <input id="product-initial-stock" type="number" min="0" step="1" class="nexora-input" placeholder="0"
                                                   x-model="form.initial_stock" x-on:input="syncSerials()">
                                            <p class="mt-1 text-xs text-zinc-500">Creates the first batch in Stores.</p>
                                            <p x-show="errors.initial_stock" x-text="errors.initial_stock" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="product-cost" class="{{ $labelClasses }}">Cost price <span class="font-normal text-zinc-400">(Per unit)</span></label>
                                            <div class="relative">
                                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rs.</span>
                                                <input id="product-cost" type="number" min="0" step="0.01" class="nexora-input pl-10" placeholder="Per unit"
                                                       x-model="form.cost_price" x-bind:required="Number(form.initial_stock) > 0">
                                            </div>
                                            <p x-show="errors.cost_price" x-text="errors.cost_price" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </div>
                                </template>

                                <div>
                                    <label for="product-low-stock" class="{{ $labelClasses }}">Low stock alert</label>
                                    <input id="product-low-stock" type="number" min="0" step="1" class="nexora-input" x-model="form.low_stock_alert">
                                    <p x-show="errors.low_stock_alert" x-text="errors.low_stock_alert" x-cloak class="{{ $errorClasses }}"></p>
                                </div>
                                <div>
                                    <label for="product-warranty" class="{{ $labelClasses }}">Warranty <span class="font-normal text-zinc-400">(months, 0 = none)</span></label>
                                    <input id="product-warranty" type="number" min="0" step="1" class="nexora-input" x-model="form.warranty_months">
                                    <p x-show="errors.warranty_months" x-text="errors.warranty_months" x-cloak class="{{ $errorClasses }}"></p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink" x-bind:class="isEditing && hasBatches && 'cursor-not-allowed opacity-60'">
                                        <input type="checkbox" class="h-4 w-4 rounded-sm border-zinc-300 accent-brand"
                                               x-model="form.track_serial" x-on:change="syncSerials()" x-bind:disabled="isEditing && hasBatches">
                                        Track Serial Numbers
                                    </label>
                                    <p class="mt-1 text-xs text-zinc-500" x-show="isEditing && hasBatches">Can't be changed once the product has stock batches.</p>
                                    <p x-show="errors.track_serial" x-text="errors.track_serial" x-cloak class="{{ $errorClasses }}"></p>
                                </div>

                                <template x-if="! isEditing && form.track_serial && form.serials.length > 0">
                                    <div class="sm:col-span-2">
                                        <label class="{{ $labelClasses }}">Serial numbers <span class="font-normal text-zinc-400">(one per unit)</span></label>
                                        <p x-show="errors.serials" x-text="errors.serials" class="{{ $errorClasses }} mb-2"></p>
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <template x-for="(serial, index) in form.serials" x-bind:key="index">
                                                <div>
                                                    <input type="text" class="nexora-input" maxlength="100" required
                                                           x-bind:placeholder="`Serial #${index + 1}`" x-bind:aria-label="`Serial number ${index + 1}`"
                                                           x-model="form.serials[index]">
                                                    <p x-show="errors[`serials.${index}`]" x-text="errors[`serials.${index}`]" class="{{ $errorClasses }}"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <div class="flex items-center gap-3 sm:col-span-2">
                                    <button type="button" role="switch" aria-labelledby="product-active-label" x-bind:aria-checked="form.active.toString()"
                                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                            x-bind:class="form.active ? 'bg-brand' : 'bg-zinc-300'"
                                            x-on:click="form.active = ! form.active">
                                        <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all"
                                              x-bind:class="form.active ? 'left-[1.125rem]' : 'left-0.5'"></span>
                                    </button>
                                    <span id="product-active-label" class="cursor-pointer text-sm text-ink" x-on:click="form.active = ! form.active">Active</span>
                                    <span class="text-xs text-zinc-500">Inactive products are hidden from POS.</span>
                                </div>
                            </div>
                        </section>
                    </form>

                    <x-slot:footer>
                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'product-form')">Cancel</button>
                        <button type="submit" form="product-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                            <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Product'">Save</span>
                            <span x-show="saving" x-cloak>Saving…</span>
                        </button>
                    </x-slot:footer>
                </x-modal>
            @endif

            {{-- Stock Batches --}}
            <x-modal name="product-batches" max-width="4xl" x-model="batchesOpen">
                <x-slot:title>Stock Batches</x-slot:title>

                <div class="space-y-4">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start" x-show="product">
                        <div>
                            <div class="font-medium text-ink" x-text="product?.name"></div>
                            <div class="text-xs text-zinc-500">
                                <span x-text="product?.sku"></span>
                                · Product price <span x-text="money(product?.selling_price)"></span>
                            </div>
                            <div class="mt-1 text-sm">
                                <span class="font-semibold text-ink" x-text="product?.total_stock"></span> <span class="text-zinc-500">in stock</span>
                                <span class="text-xs text-zinc-500">· Stores <span x-text="product?.stores_stock"></span> · Showroom <span x-text="product?.showroom_stock"></span></span>
                            </div>
                        </div>
                        @if ($canEditBatches)
                            <button type="button" class="nexora-btn nexora-btn-outline shrink-0" x-on:click="startAddBatch()" x-show="batchForm?.mode !== 'add'">
                                <x-lucide-plus class="h-4 w-4" /> Add Batch
                            </button>
                        @endif
                    </div>

                    <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                    <p x-show="! batchForm && batchError" x-text="batchError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    @if ($canEditBatches)
                        <template x-if="batchForm">
                            <form class="rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveBatch()">
                                <h4 class="mb-3 font-prata text-sm text-ink" x-text="batchForm.mode === 'add' ? 'Add Batch (lands in Stores)' : 'Edit Batch'"></h4>
                                <p x-show="batchError" x-text="batchError" class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                                <div class="grid gap-3 sm:grid-cols-4">
                                    <div>
                                        <label class="{{ $labelClasses }}" for="batch-cost">Cost price *</label>
                                        <input id="batch-cost" type="number" min="0" step="0.01" class="nexora-input" x-model="batchForm.cost_price" required>
                                        <p x-show="batchErrors.cost_price" x-text="batchErrors.cost_price" class="{{ $errorClasses }}"></p>
                                    </div>
                                    <div>
                                        <label class="{{ $labelClasses }}" for="batch-price">Selling price</label>
                                        <input id="batch-price" type="number" min="0" step="0.01" class="nexora-input" placeholder="Use product price" x-model="batchForm.selling_price">
                                        <p x-show="batchErrors.selling_price" x-text="batchErrors.selling_price" class="{{ $errorClasses }}"></p>
                                    </div>

                                    <template x-if="batchForm.mode === 'edit'">
                                        <div class="contents">
                                            <div>
                                                <label class="{{ $labelClasses }}" for="batch-total">Total qty *</label>
                                                <input id="batch-total" type="number" min="0" step="1" class="nexora-input disabled:bg-zinc-100" x-model="batchForm.total_qty" x-bind:disabled="product?.track_serial" required>
                                                <p x-show="batchErrors.total_qty" x-text="batchErrors.total_qty" class="{{ $errorClasses }}"></p>
                                            </div>
                                            <div>
                                                <label class="{{ $labelClasses }}" for="batch-remaining">Remaining qty *</label>
                                                <input id="batch-remaining" type="number" min="0" step="1" class="nexora-input disabled:bg-zinc-100" x-model="batchForm.remaining_qty" x-bind:disabled="product?.track_serial" required>
                                                <p x-show="batchErrors.remaining_qty" x-text="batchErrors.remaining_qty" class="{{ $errorClasses }}"></p>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="batchForm.mode === 'add'">
                                        <div class="sm:col-span-2">
                                            <label class="{{ $labelClasses }}" for="batch-qty" x-text="product?.track_serial ? 'Units (one serial each) *' : 'Qty *'"></label>
                                            <input id="batch-qty" type="number" min="1" step="1" class="nexora-input" x-model="batchForm.qty"
                                                   x-on:input="product?.track_serial && syncBatchSerials()" required>
                                            <p x-show="batchErrors.qty" x-text="batchErrors.qty" class="{{ $errorClasses }}"></p>
                                            <p x-show="batchErrors.serials" x-text="batchErrors.serials" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </template>

                                    <template x-if="batchForm.mode === 'add' && product?.track_serial && batchForm.serials.length > 0">
                                        <div class="grid gap-2 sm:col-span-4 sm:grid-cols-3">
                                            <template x-for="(serial, index) in batchForm.serials" x-bind:key="index">
                                                <div>
                                                    <input type="text" class="nexora-input" maxlength="100" required
                                                           x-bind:placeholder="`Serial #${index + 1}`" x-bind:aria-label="`Serial number ${index + 1}`"
                                                           x-model="batchForm.serials[index]">
                                                    <p x-show="batchErrors[`serials.${index}`]" x-text="batchErrors[`serials.${index}`]" class="{{ $errorClasses }}"></p>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <div class="sm:col-span-4">
                                        <label class="{{ $labelClasses }}" for="batch-note">Note</label>
                                        <input id="batch-note" type="text" class="nexora-input" maxlength="500" x-model="batchForm.note">
                                        <p x-show="batchErrors.note" x-text="batchErrors.note" class="{{ $errorClasses }}"></p>
                                    </div>
                                </div>

                                <p class="mt-2 text-xs text-zinc-500" x-show="batchForm.mode === 'edit' && ! product?.track_serial">
                                    Changing the remaining qty corrects the product's stock and is logged as a stock adjustment.
                                </p>

                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="batchForm = null; batchErrors = {}">Cancel</button>
                                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="savingBatch">
                                        <span x-show="! savingBatch" x-text="batchForm.mode === 'add' ? 'Add Batch' : 'Save Batch'"></span>
                                        <span x-show="savingBatch">Saving…</span>
                                    </button>
                                </div>
                            </form>
                        </template>
                    @endif

                    <div class="overflow-x-auto rounded-lg border border-zinc-200">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    <th class="px-4 py-2.5">Received</th>
                                    <th class="px-4 py-2.5">Location</th>
                                    <th class="px-4 py-2.5 text-right">Cost</th>
                                    <th class="whitespace-nowrap px-4 py-2.5 text-right">Selling Price</th>
                                    <th class="px-4 py-2.5 text-right">Remaining / Total</th>
                                    <th class="px-4 py-2.5">Status</th>
                                    <th class="px-4 py-2.5">Note</th>
                                    <th class="px-4 py-2.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <tr x-show="loadingBatches">
                                    <td colspan="8" class="px-4 py-8 text-center text-zinc-400">Loading…</td>
                                </tr>
                                <tr x-show="! loadingBatches && batches.length === 0" x-cloak>
                                    <td colspan="8" class="px-4 py-8 text-center text-zinc-400">No batches yet.</td>
                                </tr>
                                <template x-for="batch in batches" x-bind:key="batch.id">
                                    <tr class="hover:bg-zinc-50" x-bind:class="batchForm?.id === batch.id && 'bg-brand-light'">
                                        <td class="whitespace-nowrap px-4 py-2.5" x-text="batch.received_at"></td>
                                        <td class="px-4 py-2.5">
                                            <span class="badge" x-bind:class="batch.location === 'showroom' ? 'badge-info' : 'badge-default'" x-text="locationLabel(batch.location)"></span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(batch.cost_price)"></td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                                            <span x-show="batch.selling_price !== null" x-text="money(batch.selling_price)"></span>
                                            <span x-show="batch.selling_price === null" class="text-xs text-zinc-400">Product price</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums">
                                            <span class="font-medium text-ink" x-text="batch.remaining_qty"></span>
                                            <span class="text-zinc-400">/ <span x-text="batch.total_qty"></span></span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="badge" x-bind:class="batch.status === 'active' ? 'badge-success' : 'badge-default'" x-text="batch.status === 'active' ? 'Active' : 'Depleted'"></span>
                                        </td>
                                        <td class="min-w-[8rem] px-4 py-2.5 text-xs text-zinc-500" x-text="batch.note || '—'"></td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                            <button type="button" class="{{ $iconButtonClasses }} inline-flex items-center gap-1 text-xs"
                                                    x-show="product?.track_serial" x-on:click="openSerials(batch)"
                                                    title="Serial numbers" x-bind:aria-label="`Serial numbers of batch received ${batch.received_at}`">
                                                <x-lucide-hash class="h-4 w-4" />
                                                <span x-text="batch.units_count ?? ''"></span>
                                            </button>
                                            @if ($canEditBatches)
                                                <button type="button" class="{{ $iconButtonClasses }}" x-on:click="startEditBatch(batch)"
                                                        title="Edit batch" x-bind:aria-label="`Edit batch received ${batch.received_at}`">
                                                    <x-lucide-pencil class="h-4 w-4" />
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-modal>

            {{-- Serial Numbers --}}
            <x-modal name="product-serials" max-width="lg" x-model="serialsOpen">
                <x-slot:title>Serial Numbers</x-slot:title>

                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-sm">
                            <div class="font-medium text-ink" x-text="product?.name"></div>
                            <div class="text-xs text-zinc-500" x-show="batch">
                                Batch received <span x-text="batch?.received_at"></span> ·
                                <span x-text="locationLabel(batch?.location)"></span> ·
                                <span x-text="batch?.remaining_qty"></span> of <span x-text="batch?.total_qty"></span> remaining
                            </div>
                        </div>
                        @if ($canEditBatches)
                            <button type="button" class="nexora-btn nexora-btn-outline shrink-0 px-3 py-1.5 text-xs" x-on:click="startAddSerials()" x-show="! newSerials">
                                <x-lucide-plus class="h-3.5 w-3.5" /> Add More Serials
                            </button>
                        @endif
                    </div>

                    <p x-show="unitError" x-text="unitError" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    @if ($canEditBatches)
                        <template x-if="newSerials">
                            <form class="rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveNewSerials()">
                                <div class="mb-3 flex items-end gap-3">
                                    <div class="w-28">
                                        <label class="{{ $labelClasses }}" for="new-serial-count">How many?</label>
                                        <input id="new-serial-count" type="number" min="1" step="1" class="nexora-input" x-model="newSerials.count" x-on:input="syncNewSerials()">
                                    </div>
                                    <p class="pb-2 text-xs text-zinc-500">Added to this batch at its location.</p>
                                </div>
                                <p x-show="unitErrors.serials" x-text="unitErrors.serials" class="{{ $errorClasses }} mb-2"></p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <template x-for="(serial, index) in newSerials.serials" x-bind:key="index">
                                        <div>
                                            <input type="text" class="nexora-input" maxlength="100" required
                                                   x-bind:placeholder="`Serial #${index + 1}`" x-bind:aria-label="`New serial number ${index + 1}`"
                                                   x-model="newSerials.serials[index]">
                                            <p x-show="unitErrors[`serials.${index}`]" x-text="unitErrors[`serials.${index}`]" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </template>
                                </div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="newSerials = null; unitErrors = {}">Cancel</button>
                                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="savingUnits">
                                        <span x-show="! savingUnits">Add Serials</span>
                                        <span x-show="savingUnits">Saving…</span>
                                    </button>
                                </div>
                            </form>
                        </template>
                    @endif

                    <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                        <li x-show="loadingUnits" class="px-4 py-6 text-center text-sm text-zinc-400">Loading…</li>
                        <li x-show="! loadingUnits && units.length === 0" x-cloak class="px-4 py-6 text-center text-sm text-zinc-400">No serial numbers in this batch.</li>
                        <template x-for="unit in units" x-bind:key="unit.id">
                            <li class="flex flex-wrap items-center gap-2 px-4 py-2.5">
                                <template x-if="editingUnitId === unit.id">
                                    <form class="flex flex-1 flex-wrap items-start gap-2" x-on:submit.prevent="saveUnit(unit)">
                                        <div class="min-w-[10rem] flex-1">
                                            <input type="text" class="nexora-input py-1.5" maxlength="100" x-model="unitSerial" required aria-label="Serial number" x-init="$nextTick(() => $el.focus())">
                                            <p x-show="unitErrors.serial_number" x-text="unitErrors.serial_number" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <button type="submit" class="nexora-btn nexora-btn-primary px-3 py-1.5 text-xs disabled:opacity-60" x-bind:disabled="savingUnits">Save</button>
                                        <button type="button" class="nexora-btn nexora-btn-outline px-3 py-1.5 text-xs" x-on:click="editingUnitId = null; unitErrors = {}">Cancel</button>
                                    </form>
                                </template>
                                <template x-if="editingUnitId !== unit.id">
                                    <div class="flex flex-1 flex-wrap items-center gap-2">
                                        <span class="flex-1 font-mono text-sm text-ink" x-text="unit.serial_number"></span>
                                        <span class="badge" x-bind:class="unitStatusVariant(unit.status)" x-text="unitStatusLabel(unit.status)"></span>
                                        <span class="badge" x-bind:class="unit.location === 'showroom' ? 'badge-info' : 'badge-default'" x-text="locationLabel(unit.location)"></span>
                                        <span class="flex">
                                            @if ($canEditBatches)
                                                <button type="button" class="{{ $iconButtonClasses }}" x-on:click="editUnit(unit)" x-bind:aria-label="`Edit serial ${unit.serial_number}`" title="Edit serial">
                                                    <x-lucide-pencil class="h-4 w-4" />
                                                </button>
                                            @endif
                                            @if ($canDeleteUnits)
                                                <button type="button" class="{{ $iconButtonClasses }}" x-on:click="confirmDeleteUnit(unit)" x-bind:disabled="unit.status !== 'in_stock'"
                                                        x-bind:title="unit.status === 'in_stock' ? 'Remove unit' : 'Only in-stock units can be removed'"
                                                        x-bind:aria-label="`Remove serial ${unit.serial_number}`">
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                </button>
                                            @endif
                                        </span>
                                    </div>
                                </template>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-modal>

            @if ($canDeleteUnits)
                <x-confirm-dialog name="delete-unit" title="Remove serial?" confirm-label="Remove" />
            @endif
            @if ($canDelete)
                <x-confirm-dialog name="delete-product" title="Delete product?" confirm-label="Delete" />
            @endif
        </div>
    </div>
</x-layouts.app>
