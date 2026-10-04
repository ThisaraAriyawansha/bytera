@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-start']) }}>
    <div class="min-w-0">
        <h1 class="font-prata text-2xl text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-zinc-500">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div {{ $actions->attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
            {{ $actions }}
        </div>
    @endisset
</div>
