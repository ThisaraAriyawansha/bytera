@props([
    'name' => null,
    'options' => [],
    'value' => null,
    'placeholder' => 'Search…',
    'emptyLabel' => null,
    'searchUrl' => null,
    'noResults' => 'No matches found',
])

{{--
    Options may be `['value' => 'Label']` or a list of `['value' => …, 'label' => …, 'description' => …]`.
    Bind with `x-model` from a parent Alpine scope, or submit via `name` in a plain form.
--}}
@php
    $normalizedOptions = collect($options)
        ->map(fn ($option, $key) => is_array($option) ? $option : ['value' => $key, 'label' => $option])
        ->values()
        ->all();
@endphp

<div x-data="searchableSelect(@js([
        'options' => $normalizedOptions,
        'value' => $name ? old($name, $value) : $value,
        'searchUrl' => $searchUrl,
        'emptyLabel' => $emptyLabel,
     ]))"
     x-modelable="value"
     {{ $attributes->whereStartsWith('x-model') }}
     x-on:click.outside="close()"
     {{ $attributes->whereDoesntStartWith('x-model')->merge(['class' => 'relative']) }}>

    <input type="hidden" x-ref="hidden" @if ($name) name="{{ $name }}" @endif x-bind:value="value ?? ''">

    <div class="relative">
        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
        <input type="text" class="nexora-input pl-9 pr-8" autocomplete="off" role="combobox"
               placeholder="{{ $placeholder }}"
               x-model="query"
               x-bind:aria-expanded="open"
               x-on:focus="openList()"
               x-on:click="openList()"
               x-on:input="onInput()"
               x-on:keydown.arrow-down.prevent="move(1)"
               x-on:keydown.arrow-up.prevent="move(-1)"
               x-on:keydown.enter.prevent="open && choose(choices[highlighted])"
               x-on:keydown.escape="open && ($event.stopPropagation(), close())"
               x-on:keydown.tab="close()">
        <x-lucide-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400 transition-transform"
                               x-bind:class="{ 'rotate-180': open }" />
    </div>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-30 mt-1 w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-lg">
        <ul x-ref="list" role="listbox" class="max-h-60 overflow-y-auto py-1 text-sm">
            <template x-for="(option, index) in choices" :key="String(option.value) + index">
                <li role="option"
                    x-bind:aria-selected="isSelected(option)"
                    x-on:mousedown.prevent="choose(option)"
                    x-on:mouseenter="highlighted = index"
                    class="flex cursor-pointer items-start justify-between gap-2 px-3 py-2"
                    x-bind:class="highlighted === index ? 'bg-brand-light text-brand' : 'text-ink'">
                    <span class="min-w-0">
                        <span class="block truncate" x-bind:class="{ 'text-zinc-500': option.value === null }" x-text="option.label"></span>
                        <span x-show="option.description" class="block truncate text-xs text-zinc-500" x-text="option.description"></span>
                    </span>
                    <x-lucide-check x-show="isSelected(option)" class="mt-0.5 h-4 w-4 shrink-0 text-brand" />
                </li>
            </template>
        </ul>
        <p x-show="loading" class="px-3 py-2 text-xs text-zinc-500">Searching…</p>
        <p x-show="! loading && choices.length === 0" class="px-3 py-2 text-xs text-zinc-500">{{ $noResults }}</p>
    </div>
</div>
