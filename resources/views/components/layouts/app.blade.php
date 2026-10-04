<x-layouts.base class="overflow-hidden">
    <x-slot:head>{{ $head ?? '' }}</x-slot:head>

    <div x-data="{ sidebarOpen: false }"
         x-on:keydown.escape.window="sidebarOpen = false"
         x-effect="document.body.classList.toggle('overflow-hidden', sidebarOpen)"
         class="flex h-[100dvh] overflow-hidden">

        {{-- Mobile drawer overlay --}}
        <div x-show="sidebarOpen" x-cloak
             x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             x-on:click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"
             aria-hidden="true"></div>

        <x-layouts.app.sidebar :navigation="$navigation" :shop="$shop" />

        <div class="flex min-w-0 flex-1 flex-col">
            <x-layouts.app.header :shop="$shop" />

            <main class="flex-1 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
