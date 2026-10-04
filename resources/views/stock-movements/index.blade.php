@php
    use App\Models\StockMovement;
    use App\Models\StockOut;

    $typeVariants = [
        'grn' => 'success',
        'transfer' => 'info',
        'stock_out' => 'warning',
        'sale' => 'default',
        'sale_cancel' => 'danger',
        'batch_edit' => 'default',
    ];
    $total = $movements->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8">
        <x-page-header title="Stock Movements" :subtitle="'Every stock in, out and transfer · '.number_format($total).' '.str('movement')->plural($total).' in this period'">
            <x-slot:actions>
                <a href="{{ route('stock-movements.export', request()->query()) }}" class="nexora-btn nexora-btn-outline">
                    <x-lucide-download class="h-4 w-4" /> Export CSV
                </a>
            </x-slot:actions>
        </x-page-header>

        <form method="GET" action="{{ route('stock-movements.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search product, SKU, or reference…" :value="$search" aria-label="Search stock movements" class="lg:max-w-sm lg:flex-1" />
            <label class="lg:w-44">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Type</span>
                <select name="type" class="nexora-input" onchange="this.form.requestSubmit()">
                    <option value="">All types</option>
                    @foreach (StockMovement::REFERENCE_TYPES as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3 text-right">Qty</th>
                            <th class="px-5 py-3">Location</th>
                            <th class="px-5 py-3">By</th>
                            <th class="whitespace-nowrap px-5 py-3">Recipient / Reason</th>
                            <th class="px-5 py-3">Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($movements as $movement)
                            @php($reference = $references[$movement->id])
                            <tr class="align-top hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $movement->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <x-badge :variant="$typeVariants[$movement->reference_type] ?? 'default'">{{ StockMovement::REFERENCE_TYPES[$movement->reference_type] ?? $movement->reference_type }}</x-badge>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="font-medium text-ink">{{ $movement->product?->name ?? 'Deleted product' }}</div>
                                    <div class="text-xs text-zinc-500">{{ $movement->product?->sku }}</div>
                                </td>
                                <td @class([
                                    'whitespace-nowrap px-5 py-3 text-right font-medium tabular-nums',
                                    'text-green-600' => $movement->qty > 0,
                                    'text-red-600' => $movement->qty < 0,
                                ])>{{ $movement->qty > 0 ? '+' : '' }}{{ number_format($movement->qty) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $movement->locationLabel() }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $movement->performed_by_name }}</td>
                                <td class="px-5 py-3">
                                    @if ($movement->recipient)
                                        <div class="text-ink">{{ $movement->recipient }}</div>
                                        <div class="text-xs text-zinc-500">
                                            {{ StockOut::REASONS[$movement->reason] ?? $movement->reason }}{{ $movement->job_no ? ' · '.$movement->job_no : '' }}{{ $movement->reason_detail ? ' · '.$movement->reason_detail : '' }}
                                        </div>
                                    @elseif ($movement->supplier_name)
                                        <div class="text-xs text-zinc-500">Supplier: <span class="text-ink">{{ $movement->supplier_name }}</span></div>
                                    @elseif ($movement->reference_type === 'batch_edit')
                                        <div class="text-xs text-zinc-500">{{ $movement->note }}</div>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    @if ($reference['url'])
                                        <a href="{{ $reference['url'] }}" class="font-medium text-brand hover:underline">{{ $reference['number'] }}</a>
                                    @else
                                        <span class="text-zinc-500">{{ $reference['number'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No stock movements match "'.$search.'" in this period.' : 'No stock movements in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$movements" />
        </div>
    </div>
</x-layouts.app>
