@php
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $iconButtonClasses = 'rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-zinc-500';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8"
         x-data="serviceForm(@js([
             'modal' => 'service-form',
             'storeUrl' => route('services.store'),
         ]))">
        <x-page-header title="Services" subtitle="Repair and labour services you can add to a bill.">
            @if ($canEdit)
                <x-slot:actions>
                    <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="openAdd()">
                        <x-lucide-plus class="h-4 w-4" /> Add Service
                    </button>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-flash />

        <form method="GET" action="{{ route('services.index') }}" class="mb-4 max-w-md">
            <x-search-input placeholder="Search by name, description or field…" :value="$search" aria-label="Search services" />
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Service</th>
                            <th class="whitespace-nowrap px-5 py-3 text-right">Default Price</th>
                            <th class="px-5 py-3">Custom Fields</th>
                            <th class="px-5 py-3">Status</th>
                            @if ($canEdit || $canDelete)
                                <th class="px-5 py-3 text-right">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($services as $service)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink">{{ $service->name }}</div>
                                    @if ($service->description)
                                        <div class="text-xs text-zinc-500">{{ $service->description }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($service->default_price) }}</td>
                                <td class="px-5 py-3">
                                    @if (empty($service->custom_fields))
                                        <span class="text-zinc-400">—</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($service->custom_fields as $field)
                                                <span class="badge badge-default" title="{{ $fieldTypes[$field['type']] ?? $field['type'] }}{{ $field['required'] ? ' · required' : '' }}">
                                                    {{ $field['label'] }}@if ($field['required'])<span class="text-brand">*</span>@endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$service->active ? 'success' : 'default'">{{ $service->active ? 'Active' : 'Inactive' }}</x-badge>
                                </td>
                                @if ($canEdit || $canDelete)
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        @if ($canEdit)
                                            <button type="button" class="{{ $iconButtonClasses }}"
                                                    x-on:click="openEdit(@js([
                                                        'name' => $service->name,
                                                        'default_price' => (float) $service->default_price,
                                                        'description' => $service->description,
                                                        'active' => $service->active,
                                                        'custom_fields' => $service->custom_fields ?? [],
                                                        'updateUrl' => route('services.update', $service),
                                                    ]))"
                                                    aria-label="Edit {{ $service->name }}">
                                                <x-lucide-pencil class="h-4 w-4" />
                                            </button>
                                        @endif
                                        @if ($canDelete)
                                            <button type="button" class="{{ $iconButtonClasses }}"
                                                    x-on:click="$dispatch('open-modal', @js([
                                                        'name' => 'delete-service',
                                                        'message' => "Delete the service \"{$service->name}\"? Existing bills and jobs keep their copy of it.",
                                                        'action' => route('services.destroy', $service),
                                                    ]))"
                                                    aria-label="Delete {{ $service->name }}">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No services match your search.' : 'No services yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$services" />
        </div>

        @if ($canEdit)
            <x-modal name="service-form" max-width="2xl">
                <x-slot:title><span x-text="isEditing ? 'Edit Service' : 'Add Service'">Add Service</span></x-slot:title>

                <form id="service-form" class="space-y-5" x-on:submit.prevent="save()">
                    <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-2">
                            <label for="service-name" class="{{ $labelClasses }}">Name *</label>
                            <input id="service-name" type="text" class="nexora-input" placeholder="e.g. Laptop Screen Replacement" maxlength="150" x-model="form.name" required autofocus>
                            <p x-show="errors.name" x-text="errors.name" x-cloak class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="service-price" class="{{ $labelClasses }}">Default price</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rs.</span>
                                <input id="service-price" type="number" min="0" step="0.01" class="nexora-input pl-10" placeholder="0" x-model="form.default_price">
                            </div>
                            <p x-show="errors.default_price" x-text="errors.default_price" x-cloak class="{{ $errorClasses }}"></p>
                        </div>
                    </div>

                    <div>
                        <label for="service-description" class="{{ $labelClasses }}">Description</label>
                        <textarea id="service-description" rows="2" class="nexora-input" maxlength="1000" x-model="form.description"></textarea>
                        <p x-show="errors.description" x-text="errors.description" x-cloak class="{{ $errorClasses }}"></p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" role="switch" aria-labelledby="service-active-label" x-bind:aria-checked="form.active.toString()"
                                class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors"
                                x-bind:class="form.active ? 'bg-brand' : 'bg-zinc-300'"
                                x-on:click="form.active = ! form.active">
                            <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all"
                                  x-bind:class="form.active ? 'left-[1.125rem]' : 'left-0.5'"></span>
                        </button>
                        <span id="service-active-label" class="cursor-pointer text-sm text-ink" x-on:click="form.active = ! form.active">Active</span>
                        <span class="text-xs text-zinc-500">Only active services appear in POS.</span>
                    </div>

                    {{-- Custom fields builder --}}
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <div>
                                <h4 class="font-prata text-sm text-ink">Custom fields</h4>
                                <p class="text-xs text-zinc-500">Details staff fill in when they add this service to a bill.</p>
                            </div>
                            <button type="button" class="nexora-btn nexora-btn-outline shrink-0 px-3 py-1.5 text-xs" x-on:click="addField()">
                                <x-lucide-plus class="h-3.5 w-3.5" /> Add Field
                            </button>
                        </div>
                        <p x-show="errors.custom_fields" x-text="errors.custom_fields" x-cloak class="{{ $errorClasses }} mb-2"></p>

                        <p x-show="form.custom_fields.length === 0" class="rounded border border-dashed border-zinc-300 px-3 py-4 text-center text-sm text-zinc-400">
                            No custom fields.
                        </p>

                        <div class="space-y-3">
                            <template x-for="(field, index) in form.custom_fields" x-bind:key="field.key">
                                <div class="rounded border border-zinc-200 bg-zinc-50 p-3">
                                    <div class="grid gap-3 sm:grid-cols-12">
                                        <div class="sm:col-span-5">
                                            <label class="{{ $labelClasses }}" x-bind:for="`field-label-${field.key}`">Label *</label>
                                            <input type="text" class="nexora-input" placeholder="e.g. Model Number" maxlength="100" required
                                                   x-bind:id="`field-label-${field.key}`" x-model="field.label">
                                            <p x-show="fieldError(index, 'label')" x-text="fieldError(index, 'label')" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div class="sm:col-span-3">
                                            <label class="{{ $labelClasses }}" x-bind:for="`field-type-${field.key}`">Type</label>
                                            <select class="nexora-input" x-bind:id="`field-type-${field.key}`" x-model="field.type">
                                                @foreach ($fieldTypes as $type => $typeLabel)
                                                    <option value="{{ $type }}">{{ $typeLabel }}</option>
                                                @endforeach
                                            </select>
                                            <p x-show="fieldError(index, 'type')" x-text="fieldError(index, 'type')" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div class="flex items-end justify-between gap-2 sm:col-span-4">
                                            <label class="flex cursor-pointer items-center gap-2 pb-2.5 text-sm text-zinc-700">
                                                <input type="checkbox" class="h-4 w-4 rounded-sm border-zinc-300 accent-brand" x-model="field.required">
                                                Required
                                            </label>
                                            <div class="flex items-center pb-1">
                                                <button type="button" class="{{ $iconButtonClasses }}" x-on:click="moveField(index, -1)" x-bind:disabled="index === 0" aria-label="Move field up">
                                                    <x-lucide-arrow-up class="h-4 w-4" />
                                                </button>
                                                <button type="button" class="{{ $iconButtonClasses }}" x-on:click="moveField(index, 1)" x-bind:disabled="index === form.custom_fields.length - 1" aria-label="Move field down">
                                                    <x-lucide-arrow-down class="h-4 w-4" />
                                                </button>
                                                <button type="button" class="{{ $iconButtonClasses }}" x-on:click="removeField(index)" aria-label="Remove field">
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div x-bind:class="field.type === 'select' ? 'sm:col-span-5' : 'sm:col-span-12'">
                                            <label class="{{ $labelClasses }}" x-bind:for="`field-placeholder-${field.key}`">Placeholder</label>
                                            <input type="text" class="nexora-input" placeholder="Optional hint text" maxlength="150"
                                                   x-bind:id="`field-placeholder-${field.key}`" x-model="field.placeholder">
                                            <p x-show="fieldError(index, 'placeholder')" x-text="fieldError(index, 'placeholder')" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div class="sm:col-span-7" x-show="field.type === 'select'">
                                            <label class="{{ $labelClasses }}" x-bind:for="`field-options-${field.key}`">Options * <span class="font-normal text-zinc-400">(comma-separated)</span></label>
                                            <input type="text" class="nexora-input" placeholder="e.g. 13 inch, 14 inch, 15.6 inch"
                                                   x-bind:id="`field-options-${field.key}`" x-model="field.optionsText">
                                            <p x-show="fieldError(index, 'options')" x-text="fieldError(index, 'options')" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </form>

                <x-slot:footer>
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'service-form')">Cancel</button>
                    <button type="submit" form="service-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                        <span x-show="! saving" x-text="isEditing ? 'Save Changes' : 'Add Service'">Save</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                </x-slot:footer>
            </x-modal>
        @endif

        @if ($canDelete)
            <x-confirm-dialog name="delete-service" title="Delete service?" confirm-label="Delete" />
        @endif
    </div>
</x-layouts.app>
