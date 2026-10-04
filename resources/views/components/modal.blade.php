@props([
    'name',
    'title' => null,
    'maxWidth' => 'md',
    'show' => false,
])

{{--
    Open with `$dispatch('open-modal', '{{ $name }}')` / close with `$dispatch('close-modal', '{{ $name }}')`,
    or bind it to parent Alpine state with `x-model="someFlag"`. Optional `footer` slot for buttons.
--}}
@php
    $maxWidthClass = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '4xl' => 'max-w-4xl',
    ][$maxWidth] ?? 'max-w-md';
@endphp

<div x-data="{ show: @js($show), name: @js($name) }"
     x-modelable="show"
     {{ $attributes->whereStartsWith('x-model') }}
     x-init="$watch('show', (isOpen) => isOpen && $nextTick(() => $el.querySelector('[autofocus]')?.focus()))"
     x-on:open-modal.window="($event.detail?.name ?? $event.detail) === name && (show = true)"
     x-on:close-modal.window="($event.detail?.name ?? $event.detail) === name && (show = false)"
     x-on:keydown.escape.window="show = false"
     x-show="show" x-cloak
     x-transition.opacity.duration.150ms
     x-on:click.self="show = false"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
     role="dialog" aria-modal="true"
     {{ $attributes->whereDoesntStartWith('x-model') }}>
    <div class="mx-4 max-h-[90vh] w-full {{ $maxWidthClass }} overflow-y-auto rounded-xl bg-white animate-fadeIn">
        <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-5 py-4">
            <h3 class="font-prata text-lg text-ink">{{ $title }}</h3>
            <button type="button" class="text-zinc-400 hover:text-brand" x-on:click="show = false" aria-label="Close">
                <x-lucide-x class="h-5 w-5" />
            </button>
        </div>

        <div class="p-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div {{ $footer->attributes->merge(['class' => 'flex justify-end gap-2 border-t border-zinc-200 px-5 py-4']) }}>
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
