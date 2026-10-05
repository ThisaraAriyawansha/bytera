@php
    use App\Http\Controllers\SalaryController;

    $subtitles = [
        'issue' => 'Pay an employee — monthly, commission or both. Each payment is also recorded as a salaries expense.',
        'history' => 'Salary payments issued, with how each amount was worked out.',
        'setup' => 'Default salary type, monthly amount and commission % used to pre-fill a payment.',
    ];
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="Salary" :subtitle="$subtitles[$tab]">
            <x-slot:actions>
                <nav class="flex max-w-full gap-1 overflow-x-auto rounded-md bg-zinc-100 p-1" aria-label="Salary sections">
                    @foreach (SalaryController::TABS as $key => $label)
                        <a href="{{ route('salary.index', ['tab' => $key]) }}"
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

        @include("salary.partials.{$tab}")
    </div>
</x-layouts.app>
