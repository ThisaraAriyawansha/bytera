@props([
    'fromName' => 'from',
    'toName' => 'to',
    'from' => null,
    'to' => null,
    'autoSubmit' => false,
])

{{-- From / To date filters. Defaults to the last 30 days (SPEC §2.3), matching DateRange::fromRequest(). --}}
@php
    $range = \App\Support\DateRange::fromRequest(request(), $fromName, $toName);
    $submitOnChange = $autoSubmit ? 'this.form && this.form.requestSubmit()' : null;
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-2']) }}>
    <label class="min-w-[9rem] flex-1">
        <span class="mb-1 block text-xs font-medium text-zinc-600">From</span>
        <input type="date" name="{{ $fromName }}" class="nexora-input"
               value="{{ $from ?? $range['from']->toDateString() }}"
               @if ($submitOnChange) onchange="{{ $submitOnChange }}" @endif>
    </label>
    <label class="min-w-[9rem] flex-1">
        <span class="mb-1 block text-xs font-medium text-zinc-600">To</span>
        <input type="date" name="{{ $toName }}" class="nexora-input"
               value="{{ $to ?? $range['to']->toDateString() }}"
               @if ($submitOnChange) onchange="{{ $submitOnChange }}" @endif>
    </label>
</div>
