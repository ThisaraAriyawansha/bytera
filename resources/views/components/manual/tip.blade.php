@props(['title' => 'Tip'])

<div class="flex gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm leading-6 text-green-900">
    <x-lucide-lightbulb class="mt-0.5 h-4 w-4 shrink-0 text-green-700" />
    <div class="min-w-0"><strong class="font-semibold text-green-800">{{ $title }}:</strong> {{ $slot }}</div>
</div>
