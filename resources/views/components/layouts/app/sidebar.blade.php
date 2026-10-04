@props(['navigation', 'shop'])

@php
    $user = auth()->user();
    $linkClasses = 'flex items-center gap-2.5 rounded px-3 py-2 text-sm transition-colors';
    $activeClasses = 'bg-brand text-white font-medium';
    $inactiveClasses = 'text-zinc-600 hover:text-brand hover:bg-brand-light';
@endphp

<aside class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 -translate-x-full flex-col border-r border-zinc-200 bg-white transition-transform duration-[250ms] ease-out lg:static lg:z-auto lg:w-56 lg:translate-x-0"
       x-bind:class="{ '-translate-x-full': ! sidebarOpen }"
       aria-label="Sidebar">

    {{-- Logo --}}
    <div class="flex items-center justify-between gap-2 px-4 pt-4 pb-2">
        <a href="{{ route('dashboard') }}" class="block h-14 w-28 overflow-hidden">
            <img src="{{ asset('shop_logo/IMG_0112.PNG') }}" alt="{{ $shop->name }}" class="h-full w-28 scale-[1.4] object-contain">
        </a>
        <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand lg:hidden"
                x-on:click="sidebarOpen = false" aria-label="Close menu">
            <x-lucide-x class="h-5 w-5" />
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="sidebar-nav-scroll flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
        @foreach ($navigation as $item)
            @if (isset($item['children']))
                <div x-data="{ open: @js($item['active']) }">
                    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
                            class="{{ $linkClasses }} w-full {{ $item['active'] ? 'text-brand font-medium' : 'text-zinc-600 hover:text-brand hover:bg-brand-light' }}">
                        <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-4 w-4 shrink-0" />
                        <span class="flex-1 text-left">{{ $item['label'] }}</span>
                        <x-lucide-chevron-down class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $item['active'] ? 'rotate-180' : '' }}"
                                               x-bind:class="{ 'rotate-180': open }" />
                    </button>

                    <div class="grid transition-[grid-template-rows] duration-200 ease-out"
                         style="grid-template-rows: {{ $item['active'] ? '1fr' : '0fr' }}"
                         x-bind:style="{ gridTemplateRows: open ? '1fr' : '0fr' }">
                        <div class="overflow-hidden">
                            <div class="ml-[1.2rem] mt-0.5 space-y-0.5 border-l border-dashed border-zinc-300 pl-2">
                                @foreach ($item['children'] as $child)
                                    <a href="{{ $child['url'] }}" @if ($child['active']) aria-current="page" @endif
                                       class="{{ $linkClasses }} {{ $child['active'] ? $activeClasses : $inactiveClasses }}">
                                        <x-dynamic-component :component="'lucide-'.$child['icon']" class="h-4 w-4 shrink-0" />
                                        <span class="truncate">{{ $child['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                   class="{{ $linkClasses }} {{ $item['active'] ? $activeClasses : $inactiveClasses }}">
                    <x-dynamic-component :component="'lucide-'.$item['icon']" class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    {{-- User card, sign out, footer --}}
    <div class="space-y-1 border-t border-zinc-200 p-3">
        <a href="{{ route('profile.edit') }}"
           class="group flex items-center gap-2.5 rounded p-2 transition-colors hover:bg-zinc-50 {{ request()->routeIs('profile.*') ? 'bg-zinc-50' : '' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink font-prata text-xs text-white transition-colors group-hover:bg-brand">
                {{ $user->initials() }}
            </span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-medium text-ink">{{ $user->name ?: $user->email }}</span>
                <span class="block truncate text-xs text-zinc-500">{{ $user->role }} · View profile</span>
            </span>
        </a>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="{{ $linkClasses }} w-full text-zinc-600 hover:bg-brand-light hover:text-brand">
                <x-lucide-log-out class="h-4 w-4 shrink-0" />
                Sign out
            </button>
        </form>

        <div class="px-2 pt-1 text-[10px] leading-snug text-zinc-400">
            <p>© {{ now()->year }} {{ $shop->name }}</p>
            <p>Design &amp; Developed by plexCode</p>
        </div>
    </div>
</aside>
