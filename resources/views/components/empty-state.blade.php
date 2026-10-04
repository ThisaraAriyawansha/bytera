@props([
    'icon' => 'inbox',
    'title',
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'nexora-card flex flex-col items-center px-6 py-16 text-center']) }}>
    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-400">
        <x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" />
    </div>
    <h2 class="font-prata text-base text-ink">{{ $title }}</h2>
    @if ($message)
        <p class="mt-1 max-w-sm text-sm text-zinc-500">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
