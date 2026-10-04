@props(['shop'])

<header class="flex h-14 shrink-0 items-center justify-between gap-3 bg-brand px-4 text-white lg:border-b lg:border-zinc-200 lg:bg-white lg:px-8 lg:text-ink">
    <div class="flex min-w-0 items-center gap-2 lg:hidden">
        <button type="button" class="-ml-1.5 rounded p-1.5 hover:bg-white/10" x-on:click="sidebarOpen = true" aria-label="Open menu">
            <x-lucide-menu class="h-5 w-5" />
        </button>
        <span class="truncate font-prata text-base">{{ $shop->name }}</span>
    </div>

    <div x-data="clock" class="ml-auto flex items-center gap-1.5 whitespace-nowrap text-xs text-white/90 lg:text-sm lg:text-zinc-500">
        <x-lucide-clock class="h-4 w-4 shrink-0" />
        <span class="hidden sm:inline" x-text="date">{{ now()->format('D, M j, Y') }}</span>
        <span class="hidden sm:inline">·</span>
        <span x-text="time">{{ now()->format('g:i A') }}</span>
    </div>
</header>
