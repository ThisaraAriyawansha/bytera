@props([
    'name' => 'search',
    'placeholder' => 'Search…',
    'value' => null,
])

<div class="relative {{ $attributes->get('class') }}">
    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
    <input type="search"
           name="{{ $name }}"
           value="{{ $value ?? request($name) }}"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           {{ $attributes->except('class')->merge(['class' => 'nexora-input pl-9']) }}>
</div>
