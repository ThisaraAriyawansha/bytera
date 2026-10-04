@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $iconButtonClasses = 'rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand';
    $productCount = fn (int $count): string => number_format($count).' '.str('product')->plural($count);
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="Categories" subtitle="Main categories and their subcategories.">
            <x-slot:actions>
                <button type="button" class="nexora-btn nexora-btn-outline disabled:cursor-not-allowed disabled:opacity-50"
                        x-data x-on:click="$dispatch('add-sub-category', {})"
                        @disabled($mainCategories->isEmpty())
                        @if ($mainCategories->isEmpty()) title="Add a main category first" @endif>
                    <x-lucide-plus class="h-4 w-4" /> Add Subcategory
                </button>
                <button type="button" class="nexora-btn nexora-btn-primary" x-data x-on:click="$dispatch('add-main-category')">
                    <x-lucide-plus class="h-4 w-4" /> Add Main Category
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-flash />

        @if ($mainCategories->isEmpty())
            <x-empty-state icon="layers" title="No categories yet" message="Add a main category (e.g. Laptops), then add its subcategories (e.g. Gaming Laptops)." />
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($mainCategories as $mainCategory)
                    <section class="nexora-card flex flex-col" x-data>
                        <div class="flex items-start justify-between gap-3 border-b border-zinc-200 px-4 py-3">
                            <div class="min-w-0">
                                <h2 class="font-prata truncate text-base text-ink">{{ $mainCategory->name }}</h2>
                                @if ($mainCategory->description)
                                    <p class="text-xs text-zinc-500">{{ $mainCategory->description }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-zinc-400">
                                    {{ $mainCategory->subCategories->count() }} {{ str('subcategory')->plural($mainCategory->subCategories->count()) }}
                                    · {{ $productCount($mainCategory->products_count) }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center">
                                <button type="button" class="{{ $iconButtonClasses }}"
                                        x-on:click="$dispatch('add-sub-category', @js(['main_category_id' => $mainCategory->id]))"
                                        aria-label="Add subcategory to {{ $mainCategory->name }}" title="Add Subcategory">
                                    <x-lucide-plus class="h-4 w-4" />
                                </button>
                                <button type="button" class="{{ $iconButtonClasses }}"
                                        x-on:click="$dispatch('edit-main-category', @js([
                                            'name' => $mainCategory->name,
                                            'description' => $mainCategory->description,
                                            'updateUrl' => route('categories.main.update', $mainCategory),
                                        ]))"
                                        aria-label="Edit {{ $mainCategory->name }}">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                @if ($canDelete)
                                    @php
                                        $subNames = $mainCategory->subCategories->pluck('name');
                                        $deleteMessage = $subNames->isEmpty()
                                            ? "Delete the main category \"{$mainCategory->name}\"? This can't be undone."
                                            : "Delete \"{$mainCategory->name}\"? Its {$subNames->count()} ".str('subcategory')->plural($subNames->count())
                                                ." ({$subNames->join(', ')}) will be deleted too. This can't be undone.";
                                    @endphp
                                    <button type="button" class="{{ $iconButtonClasses }}"
                                            x-on:click="$dispatch('open-modal', @js([
                                                'name' => 'delete-category',
                                                'title' => 'Delete main category?',
                                                'message' => $deleteMessage,
                                                'action' => route('categories.main.destroy', $mainCategory),
                                            ]))"
                                            aria-label="Delete {{ $mainCategory->name }}">
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </div>

                        <ul class="flex-1 divide-y divide-zinc-100">
                            @forelse ($mainCategory->subCategories as $subCategory)
                                <li class="flex items-center justify-between gap-2 py-1.5 pl-4 pr-3 hover:bg-zinc-50">
                                    <div class="min-w-0 text-sm">
                                        <span class="text-ink">{{ $subCategory->name }}</span>
                                        <span class="ml-1 text-xs text-zinc-400">{{ $productCount($subCategory->products_count) }}</span>
                                        @if ($subCategory->description)
                                            <p class="truncate text-xs text-zinc-500">{{ $subCategory->description }}</p>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center">
                                        <button type="button" class="{{ $iconButtonClasses }}"
                                                x-on:click="$dispatch('edit-sub-category', @js([
                                                    'main_category_id' => $subCategory->main_category_id,
                                                    'name' => $subCategory->name,
                                                    'description' => $subCategory->description,
                                                    'updateUrl' => route('categories.sub.update', $subCategory),
                                                ]))"
                                                aria-label="Edit {{ $subCategory->name }}">
                                            <x-lucide-pencil class="h-3.5 w-3.5" />
                                        </button>
                                        @if ($canDelete)
                                            <button type="button" class="{{ $iconButtonClasses }}"
                                                    x-on:click="$dispatch('open-modal', @js([
                                                        'name' => 'delete-category',
                                                        'title' => 'Delete subcategory?',
                                                        'message' => "Delete the subcategory \"{$subCategory->name}\" from {$mainCategory->name}? This can't be undone.",
                                                        'action' => route('categories.sub.destroy', $subCategory),
                                                    ]))"
                                                    aria-label="Delete {{ $subCategory->name }}">
                                                <x-lucide-trash-2 class="h-3.5 w-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li class="px-4 py-4 text-center text-sm text-zinc-400">No subcategories yet.</li>
                            @endforelse
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif

        {{-- Main category form --}}
        <div x-data="recordForm(@js([
                 'modal' => 'main-category-form',
                 'storeUrl' => route('categories.main.store'),
                 'blank' => ['name' => '', 'description' => ''],
             ]))"
             x-on:add-main-category.window="openAdd()"
             x-on:edit-main-category.window="openEdit($event.detail)">
            <x-modal name="main-category-form">
                <x-slot:title><span x-text="isEditing ? 'Edit Main Category' : 'Add Main Category'">Add Main Category</span></x-slot:title>

                <form id="main-category-form" class="space-y-4" x-on:submit.prevent="save()">
                    <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                    <div>
                        <label for="main-category-name" class="{{ $labelClasses }}">Name *</label>
                        <input id="main-category-name" type="text" class="nexora-input" placeholder="e.g. Laptops" maxlength="100" x-model="form.name" required autofocus>
                        <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="main-category-description" class="{{ $labelClasses }}">Description</label>
                        <textarea id="main-category-description" rows="2" class="nexora-input" maxlength="500" placeholder="Optional" x-model="form.description"></textarea>
                        <p x-show="errors.description" x-text="errors.description" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </form>

                <x-slot:footer>
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'main-category-form')">Cancel</button>
                    <button type="submit" form="main-category-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                        <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Main Category'">Save</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                </x-slot:footer>
            </x-modal>
        </div>

        {{-- Subcategory form --}}
        <div x-data="recordForm(@js([
                 'modal' => 'sub-category-form',
                 'storeUrl' => route('categories.sub.store'),
                 'blank' => ['main_category_id' => '', 'name' => '', 'description' => ''],
             ]))"
             x-on:add-sub-category.window="openAdd($event.detail ?? {})"
             x-on:edit-sub-category.window="openEdit($event.detail)">
            <x-modal name="sub-category-form">
                <x-slot:title><span x-text="isEditing ? 'Edit Subcategory' : 'Add Subcategory'">Add Subcategory</span></x-slot:title>

                <form id="sub-category-form" class="space-y-4" x-on:submit.prevent="save()">
                    <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                    <div>
                        <label for="sub-category-main" class="{{ $labelClasses }}">Main category *</label>
                        <select id="sub-category-main" class="nexora-input" x-model.number="form.main_category_id" required>
                            <option value="">Select a main category</option>
                            @foreach ($mainCategories as $mainCategory)
                                <option value="{{ $mainCategory->id }}">{{ $mainCategory->name }}</option>
                            @endforeach
                        </select>
                        <p x-show="errors.main_category_id" x-text="errors.main_category_id" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="sub-category-name" class="{{ $labelClasses }}">Name *</label>
                        <input id="sub-category-name" type="text" class="nexora-input" placeholder="e.g. Gaming Laptops" maxlength="100" x-model="form.name" required autofocus>
                        <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="sub-category-description" class="{{ $labelClasses }}">Description</label>
                        <textarea id="sub-category-description" rows="2" class="nexora-input" maxlength="500" placeholder="Optional" x-model="form.description"></textarea>
                        <p x-show="errors.description" x-text="errors.description" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </form>

                <x-slot:footer>
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'sub-category-form')">Cancel</button>
                    <button type="submit" form="sub-category-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                        <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Subcategory'">Save</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                </x-slot:footer>
            </x-modal>
        </div>

        @if ($canDelete)
            <x-confirm-dialog name="delete-category" title="Delete category?" confirm-label="Delete" />
        @endif
    </div>
</x-layouts.app>
