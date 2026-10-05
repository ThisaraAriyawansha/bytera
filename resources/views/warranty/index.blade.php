@php
    use App\Models\Warranty;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $summaryAccent = [
        'active' => 'text-green-700',
        'expiring' => 'text-amber-600',
        'expired' => 'text-red-600',
        'claimed' => 'text-zinc-600',
    ];
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="warrantyClaim()">
        <x-page-header title="Warranty" subtitle="Warranties issued on sold items." />

        <x-flash />

        {{-- Summary counts --}}
        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach (Warranty::STATUSES as $key => $meta)
                <a href="{{ route('warranty.index', array_filter(['status' => $key, 'search' => $search])) }}"
                   class="nexora-card block px-4 py-3 transition-colors hover:border-brand {{ $status === $key ? 'border-brand ring-1 ring-brand' : '' }}">
                    <span class="block text-xs font-medium uppercase tracking-wider text-zinc-500">{{ $meta['label'] }}</span>
                    <span class="font-prata text-2xl {{ $summaryAccent[$key] }}">{{ number_format($counts[$key]) }}</span>
                </a>
            @endforeach
        </div>

        {{-- Status tabs + search --}}
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-zinc-200" aria-label="Warranty status">
                @foreach (['' => ['label' => 'All']] + Warranty::STATUSES as $key => $meta)
                    @php $isActive = ($status ?? '') === $key; @endphp
                    <a href="{{ route('warranty.index', array_filter(['status' => $key, 'search' => $search])) }}"
                       class="flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm transition-colors {{ $isActive ? 'border-brand font-medium text-brand' : 'border-transparent text-zinc-500 hover:text-ink' }}"
                       @if ($isActive) aria-current="page" @endif>
                        {{ $meta['label'] }}
                        <span class="rounded-full px-1.5 text-[11px] {{ $isActive ? 'bg-brand text-white' : 'bg-zinc-100 text-zinc-500' }}">
                            {{ number_format($key === '' ? $counts->sum() : $counts[$key]) }}
                        </span>
                    </a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('warranty.index') }}" class="flex gap-2 lg:w-96">
                @if ($status)
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <x-search-input placeholder="Search customer or product…" :value="$search" aria-label="Search warranties" class="flex-1" />
                <button type="submit" class="nexora-btn nexora-btn-outline">Search</button>
            </form>
        </div>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="whitespace-nowrap px-5 py-3">Serial No.</th>
                            <th class="px-5 py-3">Start</th>
                            <th class="px-5 py-3">End</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($warranties as $warranty)
                            @php $computed = $warranty->computedStatus(); @endphp
                            <tr class="align-top hover:bg-zinc-50">
                                <td class="px-5 py-3 text-ink">{{ $warranty->customer_name }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-ink">{{ $warranty->product_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $warranty->warranty_months }} {{ str('month')->plural($warranty->warranty_months) }}</div>
                                    @if ($computed['key'] === 'claimed' && filled($warranty->claim_note))
                                        <div class="mt-0.5 text-xs text-zinc-500">Claimed {{ $warranty->claimed_at?->format('M j, Y') }}: {{ $warranty->claim_note }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $warranty->serial_number ?: '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $warranty->start_date->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $warranty->end_date->format('M j, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <x-badge :variant="$computed['variant']">{{ $computed['label'] }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @if ($warranty->isClaimable())
                                        <button type="button" class="nexora-btn nexora-btn-outline !px-3 !py-1.5 text-xs"
                                                x-on:click="open(@js([
                                                    'claimUrl' => route('warranty.claim', $warranty),
                                                    'customer_name' => $warranty->customer_name,
                                                    'product_name' => $warranty->product_name,
                                                    'serial_number' => $warranty->serial_number,
                                                    'end_date' => $warranty->end_date->format('M j, Y'),
                                                ]))">
                                            <x-lucide-shield-check class="h-3.5 w-3.5" /> Claim
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No warranties match "'.$search.'".' : 'No warranties here yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$warranties" />
        </div>

        <x-modal name="warranty-claim" title="Claim Warranty">
            <form class="space-y-4" x-on:submit.prevent="save()">
                <div class="rounded-md bg-zinc-50 px-3 py-2 text-sm">
                    <p class="font-medium text-ink" x-text="warranty?.product_name"></p>
                    <p class="text-zinc-600" x-show="warranty?.serial_number" x-text="`Serial: ${warranty?.serial_number}`"></p>
                    <p class="text-zinc-600" x-text="`${warranty?.customer_name} · covered until ${warranty?.end_date}`"></p>
                </div>
                <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div>
                    <label for="warranty-claim-note" class="{{ $labelClasses }}">Note *</label>
                    <textarea id="warranty-claim-note" rows="3" class="nexora-input" maxlength="500" required autofocus
                              placeholder="e.g. Screen replaced under warranty" x-model="claimNote"></textarea>
                    <p x-show="errors.claim_note" x-text="errors.claim_note" class="{{ $errorClasses }}"></p>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'warranty-claim')">Cancel</button>
                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                        <span x-text="saving ? 'Saving…' : 'Claim Warranty'"></span>
                    </button>
                </div>
            </form>
        </x-modal>
    </div>
</x-layouts.app>
