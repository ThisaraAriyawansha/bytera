@props(['steps', 'label'])

{{-- A left-to-right flow diagram (top-to-bottom on phones). Each step: ['label' => ..., 'class' => colour classes, 'caption' => optional]. --}}
<ol aria-label="{{ $label }}" class="flex flex-col items-stretch gap-1.5 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
    @foreach ($steps as $step)
        <li class="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
            <span class="w-full rounded-md border px-3 py-2 text-center text-sm font-medium sm:w-auto {{ $step['class'] }}">
                {{ $step['label'] }}
                @isset($step['caption'])
                    <span class="block text-xs font-normal opacity-80">{{ $step['caption'] }}</span>
                @endisset
            </span>
            @unless ($loop->last)
                <x-lucide-arrow-down class="h-4 w-4 shrink-0 text-zinc-400 sm:hidden" aria-hidden="true" />
                <x-lucide-arrow-right class="hidden h-4 w-4 shrink-0 text-zinc-400 sm:block" aria-hidden="true" />
            @endunless
        </li>
    @endforeach
</ol>
