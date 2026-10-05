@props(['question'])

<details class="group border-b border-zinc-200 last:border-b-0">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 font-medium text-ink hover:text-brand [&::-webkit-details-marker]:hidden">
        <span>{{ $question }}</span>
        <x-lucide-chevron-down class="h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-200 group-open:rotate-180" />
    </summary>
    <div class="space-y-3 px-4 pb-4 text-sm leading-6 text-zinc-600">
        {{ $slot }}
    </div>
</details>
