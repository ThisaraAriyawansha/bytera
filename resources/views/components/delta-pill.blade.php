@props([
    'delta' => null,
    'size' => 'md',
])

{{-- Change vs the previous period: green ▲ / red ▼ with the %, grey when flat, "New" when there was nothing before. --}}
@php
    $classes = match (true) {
        $delta === null => 'bg-zinc-100 text-zinc-500',
        $delta > 0 => 'bg-green-50 text-green-700',
        $delta < 0 => 'bg-red-50 text-red-600',
        default => 'bg-zinc-100 text-zinc-500',
    };
    $text = match (true) {
        $delta === null => 'New',
        $delta > 0 => '▲ '.number_format($delta, 1).'%',
        $delta < 0 => '▼ '.number_format(abs($delta), 1).'%',
        default => '0%',
    };
    $sizeClasses = $size === 'sm' ? 'px-1.5 py-0 text-[10px]' : 'px-2 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full font-medium tabular-nums {$sizeClasses} {$classes}"]) }}>{{ $text }}</span>
