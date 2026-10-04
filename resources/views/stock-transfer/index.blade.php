@php
    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $total = $transfers->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="documentView({ editFields: ['note'] })">
        <x-page-header title="Stock Transfer" :subtitle="'Stock moved from Stores to Showroom · '.number_format($total).' '.str('transfer')->plural($total).' in this period'">
            @if ($canCreate)
                <x-slot:actions>
                    <a href="{{ route('stock-transfer.create') }}" class="nexora-btn nexora-btn-primary">
                        <x-lucide-arrow-left-right class="h-4 w-4" /> New Transfer
                    </a>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-flash />

        <form method="GET" action="{{ route('stock-transfer.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search transfer no. or staff…" :value="$search" aria-label="Search transfers" class="lg:max-w-sm lg:flex-1" />
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="whitespace-nowrap px-5 py-3">Transfer No.</th>
                            <th class="whitespace-nowrap px-5 py-3">Transferred By</th>
                            <th class="px-5 py-3 text-right">Units</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($transfers as $transfer)
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $transfer->transfer_no }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $transfer->transferred_by_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ number_format((int) $transfer->total_qty) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $transfer->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="show(@js(route('stock-transfer.show', $transfer)))"
                                            title="View" aria-label="View {{ $transfer->transfer_no }}">
                                        <x-lucide-eye class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No transfers match "'.$search.'" in this period.' : 'No transfers in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$transfers" />
        </div>

        <x-modal name="transfer-view" max-width="2xl" x-model="viewOpen">
            <x-slot:title><span x-text="record?.number ?? 'Stock Transfer'">Stock Transfer</span></x-slot:title>

            <div class="space-y-5">
                <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                <p x-show="viewErrors.form" x-text="viewErrors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                <template x-if="record">
                    <div class="space-y-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                                <dt class="text-zinc-500">Direction</dt>
                                <dd class="text-ink">Stores → Showroom</dd>
                                <dt class="text-zinc-500">Transferred By</dt>
                                <dd class="text-ink" x-text="record.transferred_by_name"></dd>
                                <dt class="text-zinc-500">Date</dt>
                                <dd class="text-ink" x-text="record.date"></dd>
                                <dt class="text-zinc-500">Note</dt>
                                <dd class="text-ink" x-text="record.note || '—'"></dd>
                            </dl>
                            @if ($canEdit)
                                <button type="button" class="nexora-btn nexora-btn-outline shrink-0" x-show="! editForm" x-on:click="startEdit()">
                                    <x-lucide-pencil class="h-4 w-4" /> Edit Note
                                </button>
                            @endif
                        </div>

                        @if ($canEdit)
                            <template x-if="editForm">
                                <form class="space-y-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveEdit()">
                                    <h4 class="font-prata text-sm text-ink">Edit Transfer</h4>
                                    <div>
                                        <label for="transfer-edit-note" class="{{ $labelClasses }}">Note</label>
                                        <input id="transfer-edit-note" type="text" class="nexora-input" maxlength="500" x-model="editForm.note">
                                        <p x-show="viewErrors.note" x-text="viewErrors.note" class="{{ $errorClasses }}"></p>
                                    </div>
                                    <p class="text-xs text-zinc-500">Only the note can be changed; items and quantities are locked. Changes are recorded in the audit log.</p>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="cancelEdit()">Cancel</button>
                                        <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="viewSaving">
                                            <span x-text="viewSaving ? 'Saving…' : 'Save Changes'"></span>
                                        </button>
                                    </div>
                                </form>
                            </template>
                        @endif

                        <div class="overflow-x-auto rounded-lg border border-zinc-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                        <th class="px-4 py-2.5">Product</th>
                                        <th class="px-4 py-2.5 text-right">Qty</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    <template x-for="item in record.items" x-bind:key="item.id">
                                        <tr class="align-top hover:bg-zinc-50">
                                            <td class="px-4 py-2.5">
                                                <div class="font-medium text-ink" x-text="item.product_name"></div>
                                                <div class="text-xs text-zinc-500" x-text="item.sku"></div>
                                                <div class="mt-1 text-xs text-zinc-500" x-show="item.serial_numbers.length > 0" x-text="`Serials: ${item.serial_numbers.join(', ')}`"></div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="item.qty"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-zinc-200 bg-zinc-50">
                                        <td class="px-4 py-2.5 text-right text-xs uppercase tracking-wider text-zinc-500">Total Units</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-ink" x-text="record.total_qty"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </x-modal>
    </div>
</x-layouts.app>
