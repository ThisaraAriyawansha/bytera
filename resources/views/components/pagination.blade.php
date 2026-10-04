@props(['paginator'])

{{-- Footer for a `->paginate(\App\Support\Pagination::PER_PAGE)` list: "Showing x–y of z", Prev / pages / Next. --}}
@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
    $paginator->appends(request()->except($paginator->getPageName()));

    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    $pages = collect([1, $last, ...range(max(1, $current - 1), min($last, $current + 1))])
        ->unique()
        ->sort()
        ->values();

    $buttonClasses = 'inline-flex min-w-[2rem] items-center justify-center rounded border px-2.5 py-1 transition-colors';
    $idleClasses = 'border-zinc-200 text-zinc-600 hover:border-brand hover:text-brand';
    $disabledClasses = 'border-zinc-200 text-zinc-300 cursor-not-allowed';
@endphp

@if ($paginator->total() > 0)
    <nav {{ $attributes->merge(['class' => 'flex flex-col items-center justify-between gap-2 border-t border-zinc-200 px-4 py-3 text-xs text-zinc-500 sm:flex-row']) }}
         aria-label="Pagination">
        <span>Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}</span>

        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="{{ $buttonClasses }} {{ $disabledClasses }}" aria-disabled="true">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $buttonClasses }} {{ $idleClasses }}">Prev</a>
            @endif

            @foreach ($pages as $index => $page)
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <span class="px-1 text-zinc-400">…</span>
                @endif

                @if ($page === $current)
                    <span class="{{ $buttonClasses }} border-brand bg-brand text-white" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="{{ $buttonClasses }} {{ $idleClasses }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $buttonClasses }} {{ $idleClasses }}">Next</a>
            @else
                <span class="{{ $buttonClasses }} {{ $disabledClasses }}" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
