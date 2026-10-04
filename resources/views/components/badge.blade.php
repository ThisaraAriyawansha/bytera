@props([
    'variant' => 'default',
    'dot' => false,
])

{{-- Status badge (SPEC §2.2). Full class names listed for Tailwind's content scan:
     badge-default badge-success badge-warning badge-danger badge-info --}}
@php
    $dotColor = [
        'default' => 'bg-zinc-400',
        'success' => 'bg-green-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-red-500',
        'info' => 'bg-blue-500',
    ][$variant] ?? 'bg-zinc-400';
@endphp

<span {{ $attributes->merge(['class' => "badge badge-{$variant}".($dot ? ' gap-1.5' : '')]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}"></span>
    @endif
    {{ $slot }}
</span>
