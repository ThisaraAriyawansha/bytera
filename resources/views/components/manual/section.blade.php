@props(['id', 'title', 'icon'])

<section id="{{ $id }}" class="scroll-mt-20 border-t border-zinc-200 pt-10 first:border-t-0 first:pt-0">
    <h2 class="flex items-center gap-2.5 font-prata text-xl text-ink sm:text-2xl">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand-light text-brand">
            <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" />
        </span>
        {{ $title }}
    </h2>
    <div class="mt-4 space-y-4 text-[15px] leading-7 text-zinc-700">
        {{ $slot }}
    </div>
</section>
