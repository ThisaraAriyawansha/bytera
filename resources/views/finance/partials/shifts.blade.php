@php
    use App\Models\Shift;
    use App\Services\ShiftService;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $sectionClasses = 'mb-2 font-prata text-sm text-ink';
@endphp

<div x-data="shiftView()">
    <form method="GET" action="{{ route('finance.index') }}" class="mb-4 space-y-2">
        <input type="hidden" name="tab" value="shifts">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search cashier or shift no…" :value="$filters['search']" aria-label="Search shifts" class="lg:max-w-xs lg:flex-1" />
            <label class="lg:w-44">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Cashier</span>
                <select name="cashier_id" class="nexora-input" onchange="this.form.requestSubmit()">
                    <option value="">All cashiers</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->cashier_id }}" @selected($filters['cashier_id'] === (int) $cashier->cashier_id)>{{ $cashier->cashier_name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="lg:w-32">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Status</span>
                <select name="status" class="nexora-input" onchange="this.form.requestSubmit()">
                    <option value="">All</option>
                    <option value="open" @selected($filters['status'] === 'open')>Open</option>
                    <option value="closed" @selected($filters['status'] === 'closed')>Closed</option>
                </select>
            </label>
            <label class="lg:w-36">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Review</span>
                <select name="review" class="nexora-input" onchange="this.form.requestSubmit()">
                    <option value="">All</option>
                    @foreach (Shift::REVIEW_STATUSES as $key => $meta)
                        <option value="{{ $key }}" @selected($filters['review'] === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <x-date-range auto-submit class="sm:max-w-md sm:flex-1" />
            <div class="flex gap-2">
                <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
                <a href="{{ route('finance.export', ['tab' => 'shifts', ...request()->only(['from', 'to', 'cashier_id', 'status', 'review', 'search'])]) }}"
                   class="nexora-btn nexora-btn-outline justify-center">
                    <x-lucide-download class="h-4 w-4" /> Export CSV
                </a>
            </div>
        </div>
    </form>

    <div class="nexora-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="whitespace-nowrap px-4 py-3">Shift No.</th>
                        <th class="px-4 py-3">Cashier</th>
                        <th class="px-4 py-3">Opened</th>
                        <th class="px-4 py-3">Closed</th>
                        <th class="px-4 py-3 text-right">Float</th>
                        <th class="px-4 py-3 text-right">Cash</th>
                        <th class="px-4 py-3 text-right">Card</th>
                        <th class="px-4 py-3 text-right">KokoPay</th>
                        <th class="px-4 py-3 text-right">Expected</th>
                        <th class="px-4 py-3 text-right">Counted</th>
                        <th class="px-4 py-3 text-right">Variance</th>
                        <th class="px-4 py-3">Review</th>
                        <th class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($shifts as $shift)
                        @php
                            $isOpen = $shift->status === 'open';
                            $openDays = $shift->openDays();
                            $variance = $shift->variance === null ? null : (float) $shift->variance;
                            $review = Shift::REVIEW_STATUSES[$shift->review_status] ?? null;
                        @endphp
                        <tr class="hover:bg-zinc-50">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">
                                {{ $shift->shift_no }}
                                @if ($shift->force_closed)
                                    <span class="block text-xs font-normal text-amber-600">Force closed</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-ink">{{ $shift->cashier_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-600">{{ $shift->opened_at->format('M j, g:i A') }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($isOpen)
                                    <x-badge variant="info">Open</x-badge>
                                    @if ($openDays >= 1)
                                        <span class="mt-0.5 flex items-center gap-1 text-xs font-medium text-amber-600">
                                            <x-lucide-triangle-alert class="h-3 w-3" /> open {{ $openDays }} {{ str('day')->plural($openDays) }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-zinc-600">{{ $shift->closed_at?->format('M j, g:i A') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ Money::format($shift->opening_float) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ Money::format($shift->cash_sales_total) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ Money::format($shift->card_sales_total) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ Money::format($shift->kokopay_sales_total) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums {{ $isOpen ? 'text-zinc-500' : 'text-ink' }}">
                                {{ Money::format($isOpen ? ShiftService::expectedCash($shift) : $shift->expected_cash) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $shift->counted_cash === null ? '—' : Money::format($shift->counted_cash) }}</td>
                            <td @class([
                                'whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums',
                                'text-red-600' => $variance !== null && $variance < 0,
                                'text-green-700' => $variance !== null && $variance > 0,
                                'text-ink' => $variance === 0.0,
                                'text-zinc-400' => $variance === null,
                            ])>
                                @if ($variance === null)
                                    —
                                @else
                                    {{ $variance > 0 ? '+' : ($variance < 0 ? '-' : '') }}{{ Money::format(abs($variance)) }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($review)
                                    <x-badge :variant="$review['variant']">{{ $review['label'] }}</x-badge>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                        x-on:click="show(@js(route('finance.shifts.show', $shift)))"
                                        title="View" aria-label="View {{ $shift->shift_no }}">
                                    <x-lucide-eye class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-5 py-12 text-center text-sm text-zinc-400">No shifts match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$shifts" />
    </div>

    {{-- Shift view --}}
    <x-modal name="shift-view" max-width="2xl" x-model="viewOpen">
        <x-slot:title>
            <span class="flex flex-wrap items-center gap-2">
                <span x-text="shift?.shift_no ?? 'Shift'">Shift</span>
                <span x-show="shift?.is_open" x-cloak class="badge badge-info font-poppins">Open</span>
                <span x-show="shift?.review_label" x-cloak class="badge font-poppins"
                      x-bind:class="{ 'badge-warning': shift?.review_status === 'pending', 'badge-success': shift?.review_status === 'approved', 'badge-danger': shift?.review_status === 'flagged' }"
                      x-text="shift?.review_label"></span>
            </span>
        </x-slot:title>

        <div class="space-y-5">
            <p x-show="loading" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
            <p x-show="notice" x-text="notice" x-cloak class="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
            <p x-show="viewErrors.form || viewErrors.shift" x-text="viewErrors.form || viewErrors.shift" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

            <template x-if="shift">
                <div class="space-y-5">
                    <div class="flex flex-col gap-1 text-sm sm:flex-row sm:justify-between">
                        <p><span class="text-zinc-500">Cashier</span> <span class="font-medium text-ink" x-text="shift.cashier_name"></span></p>
                        <p class="text-zinc-600">
                            Opened <span x-text="shift.opened_at"></span>
                            <template x-if="shift.closed_at"><span> · Closed <span x-text="shift.closed_at"></span></span></template>
                        </p>
                    </div>
                    <p x-show="shift.is_open && shift.open_days >= 1" class="flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                        <x-lucide-triangle-alert class="h-4 w-4 shrink-0" />
                        <span>This shift has been open <span x-text="shift.open_days"></span> day(s).</span>
                    </p>

                    {{-- Figures --}}
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <template x-for="figure in [
                            ['Opening float', shift.opening_float],
                            ['Cash sales', shift.cash_sales_total],
                            ['Card sales', shift.card_sales_total],
                            ['Transfer sales', shift.transfer_sales_total],
                            ['KokoPay sales', shift.kokopay_sales_total],
                            ['Cash paid out', shift.cash_expenses_total],
                            ['Sales', null],
                        ]" :key="figure[0]">
                            <div class="rounded-lg border border-zinc-200 p-3">
                                <div class="text-xs text-zinc-500" x-text="figure[0]"></div>
                                <div class="font-semibold tabular-nums text-ink" x-text="figure[1] === null ? shift.sales_count : money(figure[1])"></div>
                            </div>
                        </template>
                    </div>

                    <div class="grid grid-cols-3 gap-2 rounded-lg bg-zinc-50 p-3 text-center">
                        <div>
                            <div class="text-xs text-zinc-500" x-text="shift.is_open ? 'Expected (so far)' : 'Expected'"></div>
                            <div class="font-prata text-lg tabular-nums text-ink" x-text="money(shift.expected_cash)"></div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-500">Counted</div>
                            <div class="font-prata text-lg tabular-nums text-ink" x-text="shift.counted_cash === null ? '—' : money(shift.counted_cash)"></div>
                        </div>
                        <div>
                            <div class="text-xs text-zinc-500">Variance</div>
                            <div class="font-prata text-lg tabular-nums" x-bind:class="varianceClass(shift.variance)" x-text="varianceLabel(shift.variance)"></div>
                        </div>
                    </div>
                    <p class="-mt-3 text-xs text-zinc-400">Expected = opening float + cash sales − cash paid out.</p>

                    {{-- Notes, force close, review info --}}
                    <div class="space-y-2 text-sm" x-show="shift.open_note || shift.close_note || shift.force_closed || shift.reviewed_by_name">
                        <p x-show="shift.open_note"><span class="text-zinc-500">Open note:</span> <span x-text="shift.open_note"></span></p>
                        <p x-show="shift.close_note"><span class="text-zinc-500">Close note:</span> <span x-text="shift.close_note"></span></p>
                        <p x-show="shift.force_closed" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                            Force closed by <span class="font-medium" x-text="shift.closed_by_name"></span> (Admin Override)
                        </p>
                        <p x-show="shift.reviewed_by_name">
                            <span class="text-zinc-500">Reviewed:</span>
                            <span x-text="`${shift.review_label} by ${shift.reviewed_by_name} · ${shift.reviewed_at}`"></span>
                            <span class="block text-zinc-600" x-show="shift.review_note" x-text="shift.review_note"></span>
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap gap-2" x-show="(shift.urls.forceClose && ! forceForm) || (shift.urls.review && ! reviewForm)">
                        @if ($canForceClose)
                            <button type="button" class="nexora-btn nexora-btn-danger" x-show="shift.urls.forceClose && ! forceForm" x-on:click="startForceClose()">
                                <x-lucide-lock class="h-4 w-4" /> Force Close (Admin Override)
                            </button>
                        @endif
                        @if ($canReviewShift)
                            <button type="button" class="nexora-btn nexora-btn-outline" x-show="shift.urls.review && ! reviewForm" x-on:click="startReview('approved')">
                                <x-lucide-circle-check class="h-4 w-4" /> Approve
                            </button>
                            <button type="button" class="nexora-btn nexora-btn-outline" x-show="shift.urls.review && ! reviewForm" x-on:click="startReview('flagged')">
                                <x-lucide-flag class="h-4 w-4" /> Flag
                            </button>
                        @endif
                    </div>

                    @if ($canForceClose)
                        <template x-if="forceForm">
                            <form class="space-y-3 rounded-lg border border-red-200 bg-red-50/60 p-4" x-on:submit.prevent="saveForceClose()">
                                <h4 class="font-prata text-sm text-ink">Force Close — <span x-text="shift.shift_no"></span></h4>
                                <p class="text-sm text-zinc-600">Close this shift for <span x-text="shift.cashier_name"></span>. Expected cash is <span class="font-medium" x-text="money(shift.expected_cash)"></span>.</p>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="force-counted" class="{{ $labelClasses }}">Counted cash *</label>
                                        <input id="force-counted" type="number" min="0" step="0.01" class="nexora-input" required autofocus x-model="forceForm.counted_cash">
                                        <p x-show="forceVariance !== null" class="mt-1 text-xs" x-bind:class="varianceClass(forceVariance)" x-text="`Variance: ${varianceLabel(forceVariance)}`"></p>
                                        <p x-show="viewErrors.counted_cash" x-text="viewErrors.counted_cash" class="{{ $errorClasses }}"></p>
                                    </div>
                                    <div>
                                        <label for="force-note" class="{{ $labelClasses }}">Note</label>
                                        <input id="force-note" type="text" class="nexora-input" maxlength="500" placeholder="Why the shift was force closed" x-model="forceForm.note">
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="forceForm = null; viewErrors = {}" x-bind:disabled="saving">Cancel</button>
                                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                                        <span x-text="saving ? 'Closing…' : 'Force Close'"></span>
                                    </button>
                                </div>
                            </form>
                        </template>
                    @endif

                    @if ($canReviewShift)
                        <template x-if="reviewForm">
                            <form class="space-y-3 rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveReview()">
                                <h4 class="font-prata text-sm text-ink" x-text="reviewForm.decision === 'approved' ? 'Approve shift' : 'Flag shift'"></h4>
                                <div>
                                    <label for="review-note" class="{{ $labelClasses }}">Note</label>
                                    <textarea id="review-note" rows="2" class="nexora-input" maxlength="500" autofocus
                                              x-bind:placeholder="reviewForm.decision === 'approved' ? 'Optional note' : 'What needs looking into?'" x-model="reviewForm.note"></textarea>
                                    <p x-show="viewErrors.decision" x-text="viewErrors.decision" class="{{ $errorClasses }}"></p>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="reviewForm = null; viewErrors = {}" x-bind:disabled="saving">Cancel</button>
                                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                                        <span x-text="saving ? 'Saving…' : (reviewForm.decision === 'approved' ? 'Approve' : 'Flag')"></span>
                                    </button>
                                </div>
                            </form>
                        </template>
                    @endif

                    {{-- Cash paid out --}}
                    <div x-show="shift.payouts.length > 0">
                        <h4 class="{{ $sectionClasses }}">Cash paid out</h4>
                        <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm">
                            <template x-for="payout in shift.payouts" :key="payout.id">
                                <li class="flex justify-between gap-3 px-4 py-2">
                                    <span class="min-w-0">
                                        <span class="font-medium text-ink" x-text="payout.expense_no"></span>
                                        <span class="text-zinc-500" x-text="` · ${payout.category}`"></span>
                                        <span class="block truncate text-xs text-zinc-500" x-text="[payout.note, payout.time].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="whitespace-nowrap tabular-nums text-brand" x-text="money(payout.amount)"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Sales this shift --}}
                    <div>
                        <h4 class="{{ $sectionClasses }}">Sales this shift (<span x-text="shift.sales.length"></span>)</h4>
                        <div class="max-h-72 overflow-auto rounded-lg border border-zinc-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                        <th class="px-4 py-2">Invoice</th>
                                        <th class="px-4 py-2">Customer</th>
                                        <th class="px-4 py-2">Payment</th>
                                        <th class="px-4 py-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100">
                                    <tr x-show="shift.sales.length === 0"><td colspan="4" class="px-4 py-6 text-center text-zinc-400">No sales in this shift.</td></tr>
                                    <template x-for="sale in shift.sales" :key="sale.id">
                                        <tr class="hover:bg-zinc-50" x-bind:class="sale.is_cancelled && 'text-zinc-400'">
                                            <td class="whitespace-nowrap px-4 py-2">
                                                <span class="font-medium" x-bind:class="sale.is_cancelled ? 'line-through' : 'text-ink'" x-text="sale.invoice_no"></span>
                                                <span class="block text-xs text-zinc-400" x-text="sale.time"></span>
                                            </td>
                                            <td class="px-4 py-2" x-text="sale.customer_name"></td>
                                            <td class="whitespace-nowrap px-4 py-2" x-text="sale.is_cancelled ? 'Cancelled' : sale.payment_label"></td>
                                            <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums" x-text="money(sale.total_amount)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-modal>
</div>
