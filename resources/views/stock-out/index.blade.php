@php
    use App\Models\StockOut;
    use App\Services\StockService;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $total = $stockOuts->total();
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="documentView({ editFields: ['recipient', 'reason', 'reason_detail', 'job_id', 'note'] })">
        <x-page-header title="Stock Out" :subtitle="'Stock issued without a POS sale · '.number_format($total).' '.str('record')->plural($total).' in this period'">
            @if ($canCreate)
                <x-slot:actions>
                    <a href="{{ route('stock-out.create') }}" class="nexora-btn nexora-btn-primary">
                        <x-lucide-package-minus class="h-4 w-4" /> New Stock Out
                    </a>
                </x-slot:actions>
            @endif
        </x-page-header>

        <x-flash />

        <form method="GET" action="{{ route('stock-out.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search no., recipient, or staff…" :value="$search" aria-label="Search stock outs" class="lg:max-w-sm lg:flex-1" />
            <x-date-range auto-submit />
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
        </form>

        <div class="nexora-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                            <th class="px-5 py-3">No.</th>
                            <th class="px-5 py-3">Location</th>
                            <th class="whitespace-nowrap px-5 py-3">Issued By</th>
                            <th class="px-5 py-3">To</th>
                            <th class="px-5 py-3">Reason</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($stockOuts as $stockOut)
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $stockOut->stock_out_no }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :variant="$stockOut->location === 'showroom' ? 'info' : 'default'">{{ StockService::LOCATIONS[$stockOut->location] ?? $stockOut->location }}</x-badge>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $stockOut->issued_by_name }}</td>
                                <td class="px-5 py-3 text-ink">{{ $stockOut->recipient }}</td>
                                <td class="px-5 py-3">
                                    <div class="text-ink">{{ StockOut::REASONS[$stockOut->reason] ?? $stockOut->reason }}{{ $stockOut->job_no ? ' · '.$stockOut->job_no : '' }}</div>
                                    @if ($stockOut->reason_detail !== '')
                                        <div class="text-xs text-zinc-500">{{ $stockOut->reason_detail }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $stockOut->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="show(@js(route('stock-out.show', $stockOut)))"
                                            title="View" aria-label="View {{ $stockOut->stock_out_no }}">
                                        <x-lucide-eye class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $search !== '' ? 'No stock outs match "'.$search.'" in this period.' : 'No stock outs in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$stockOuts" />
        </div>

        <x-modal name="stock-out-view" max-width="2xl" x-model="viewOpen">
            <x-slot:title><span x-text="record?.number ?? 'Stock Out'">Stock Out</span></x-slot:title>

            <div class="space-y-5">
                <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                <p x-show="viewErrors.form" x-text="viewErrors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                <template x-if="record">
                    <div class="space-y-5">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <dl class="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-[auto_1fr]">
                                <dt class="text-zinc-500">Issued From</dt>
                                <dd><span class="badge" x-bind:class="record.location === 'showroom' ? 'badge-info' : 'badge-default'" x-text="record.location_label"></span></dd>
                                <dt class="text-zinc-500">Issued To</dt>
                                <dd class="text-ink" x-text="record.recipient"></dd>
                                <dt class="text-zinc-500">Reason</dt>
                                <dd class="text-ink">
                                    <span x-text="record.reason_label"></span><span x-show="record.reason_detail" x-text="` · ${record.reason_detail}`"></span>
                                </dd>
                                <dt class="text-zinc-500" x-show="record.job_no">Job</dt>
                                <dd class="text-ink" x-show="record.job_no" x-text="record.job_no"></dd>
                                <dt class="text-zinc-500">Issued By</dt>
                                <dd class="text-ink" x-text="record.issued_by_name"></dd>
                                <dt class="text-zinc-500">Date</dt>
                                <dd class="text-ink" x-text="record.date"></dd>
                                <dt class="text-zinc-500">Note</dt>
                                <dd class="text-ink" x-text="record.note || '—'"></dd>
                            </dl>
                            @if ($canEdit)
                                <button type="button" class="nexora-btn nexora-btn-outline shrink-0" x-show="! editForm" x-on:click="startEdit()">
                                    <x-lucide-pencil class="h-4 w-4" /> Edit Stock Out
                                </button>
                            @endif
                        </div>

                        @if ($canEdit)
                            <template x-if="editForm">
                                <form class="space-y-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveEdit()">
                                    <h4 class="font-prata text-sm text-ink">Edit Stock Out</h4>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label for="stock-out-edit-recipient" class="{{ $labelClasses }}">Issued To *</label>
                                            <input id="stock-out-edit-recipient" type="text" class="nexora-input" maxlength="255" x-model="editForm.recipient">
                                            <p x-show="viewErrors.recipient" x-text="viewErrors.recipient" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="stock-out-edit-reason" class="{{ $labelClasses }}">Reason *</label>
                                            <select id="stock-out-edit-reason" class="nexora-input" x-model="editForm.reason">
                                                @foreach (StockOut::REASONS as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <p x-show="viewErrors.reason" x-text="viewErrors.reason" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div x-show="editForm.reason === 'job'">
                                            <span class="{{ $labelClasses }}">Job *</span>
                                            <x-searchable-select :search-url="route('api.jobs.search')" placeholder="Select a job" no-results="No jobs found"
                                                                 x-model="editForm.job_id"
                                                                 x-init="seed(record.job_id ? { value: record.job_id, label: record.job_no } : null)" />
                                            <p x-show="viewErrors.job_id" x-text="viewErrors.job_id" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div>
                                            <label for="stock-out-edit-detail" class="{{ $labelClasses }}" x-text="editForm.reason === 'other' ? 'Detail *' : 'Detail'">Detail</label>
                                            <input id="stock-out-edit-detail" type="text" class="nexora-input" maxlength="255" x-model="editForm.reason_detail">
                                            <p x-show="viewErrors.reason_detail" x-text="viewErrors.reason_detail" class="{{ $errorClasses }}"></p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label for="stock-out-edit-note" class="{{ $labelClasses }}">Note</label>
                                            <input id="stock-out-edit-note" type="text" class="nexora-input" maxlength="500" x-model="editForm.note">
                                            <p x-show="viewErrors.note" x-text="viewErrors.note" class="{{ $errorClasses }}"></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-zinc-500">Location, items and quantities are locked. Changes are recorded in the audit log.</p>
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
                                        <th class="whitespace-nowrap px-4 py-2.5 text-right">Unit Cost</th>
                                        <th class="whitespace-nowrap px-4 py-2.5 text-right">Line Cost</th>
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
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(item.cost_price)"></td>
                                            <td class="whitespace-nowrap px-4 py-2.5 text-right tabular-nums" x-text="money(item.line_total)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t border-zinc-200 bg-zinc-50">
                                        <td colspan="3" class="px-4 py-2.5 text-right text-xs uppercase tracking-wider text-zinc-500">Total Cost</td>
                                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold tabular-nums text-ink" x-text="money(record.total_cost)"></td>
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
