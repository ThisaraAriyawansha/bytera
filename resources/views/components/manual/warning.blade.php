@props(['title' => 'Warning'])

<div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
    <x-lucide-triangle-alert class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
    <div class="min-w-0"><strong class="font-semibold text-amber-800">{{ $title }}:</strong> {{ $slot }}</div>
</div>
