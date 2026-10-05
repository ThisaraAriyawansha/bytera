@php
    use App\Http\Controllers\FinanceController;

    $subtitles = [
        'overview' => 'Revenue, cost of goods and profit for the period.',
        'daily' => 'Cash book: money in and out per day, with a running balance.',
        'shifts' => 'Cash drawer shifts — expected vs counted cash, force close and review.',
        'expenses' => 'Shop expenses, including cash paid out of a shift drawer.',
    ];
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="Finance" :subtitle="$subtitles[$tab]">
            <x-slot:actions>
                <nav class="flex max-w-full gap-1 overflow-x-auto rounded-md bg-zinc-100 p-1" aria-label="Finance sections">
                    @foreach (FinanceController::TABS as $key => $label)
                        <a href="{{ route('finance.index', ['tab' => $key]) }}"
                           @class([
                               'whitespace-nowrap rounded px-3 py-1.5 text-sm transition-colors',
                               'bg-white font-medium text-ink shadow-sm' => $tab === $key,
                               'text-zinc-500 hover:text-ink' => $tab !== $key,
                           ])
                           @if ($tab === $key) aria-current="page" @endif>
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
            </x-slot:actions>
        </x-page-header>

        <x-flash />

        @include("finance.partials.{$tab}")
    </div>
</x-layouts.app>
