{{-- Success / error message flashed by the last redirect (`->with('status' | 'error', …)`). --}}
@foreach (['status' => 'border-green-200 bg-green-50 text-green-700', 'error' => 'border-red-200 bg-red-50 text-red-700'] as $key => $classes)
    @if (session($key))
        <div x-data="{ show: true }" x-show="show"
             {{ $attributes->merge(['class' => "mb-6 flex items-start gap-2 rounded-md border px-3 py-2 text-sm {$classes}"]) }}
             role="{{ $key === 'error' ? 'alert' : 'status' }}">
            @if ($key === 'error')
                <x-lucide-circle-alert class="mt-0.5 h-4 w-4 shrink-0" />
            @else
                <x-lucide-circle-check class="mt-0.5 h-4 w-4 shrink-0" />
            @endif
            <span class="flex-1">{{ session($key) }}</span>
            <button type="button" class="opacity-60 hover:opacity-100" x-on:click="show = false" aria-label="Dismiss">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>
    @endif
@endforeach
