@php
    use App\Models\SalaryPayment;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

@if (! $canIssue)
    <x-empty-state icon="lock" title="You can't issue salary payments" message="Ask an Admin for the “Issue a salary payment” permission. You can still see the Payment History." />
@else
    <form class="grid gap-6 lg:grid-cols-[1fr_20rem]" x-data="salaryIssue(@js($issueConfig))" x-effect="sync()" x-on:submit.prevent="save()">
        <div class="space-y-6">
            <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

            {{-- Employee + type --}}
            <div class="nexora-card space-y-4 p-5">
                <h2 class="font-prata text-base text-ink">Employee</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="issue-employee" class="{{ $labelClasses }}">Employee *</label>
                        <select id="issue-employee" class="nexora-input" x-model="form.user_id" x-on:change="selectEmployee()" required>
                            <option value="">Choose an employee…</option>
                            <template x-for="employee in employees" :key="employee.id">
                                <option x-bind:value="employee.id" x-text="`${employee.name} · ${employee.role}`"></option>
                            </template>
                        </select>
                        <p x-show="employee" class="mt-1 text-xs text-zinc-500">Setup: <span x-text="employee?.setup_label"></span></p>
                        <p x-show="errors.user_id" x-text="errors.user_id" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <span class="{{ $labelClasses }}">Type *</span>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (SalaryPayment::TYPES as $key => $label)
                                <button type="button" class="rounded-md border px-3 py-2 text-sm transition-colors"
                                        x-bind:class="form.type === @js($key) ? 'border-ink bg-ink text-white' : 'border-zinc-200 text-ink hover:border-brand hover:text-brand'"
                                        x-on:click="form.type = @js($key)">{{ $label }}</button>
                            @endforeach
                        </div>
                        <p x-show="errors.type" x-text="errors.type" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div x-show="hasMonthly">
                        <label for="issue-monthly" class="{{ $labelClasses }}">Monthly amount (Rs.)</label>
                        <input id="issue-monthly" type="number" min="0" step="0.01" class="nexora-input" x-model="form.monthly_amount">
                    </div>
                </div>
            </div>

            {{-- Commission --}}
            <div class="nexora-card space-y-4 p-5" x-show="hasCommission" x-cloak>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-prata text-base text-ink">Commission</h2>
                    <span class="text-xs text-zinc-500" x-text="`${linked.length} linked · ${money(linkedTotal)}`"></span>
                </div>

                {{-- Linked sales / jobs --}}
                <div x-show="linked.length > 0" class="flex flex-wrap gap-2">
                    <template x-for="item in linked" :key="item.kind + item.id">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 py-1 pl-3 pr-1.5 text-xs">
                            <span class="font-medium text-ink" x-text="item.number"></span>
                            <span class="tabular-nums text-zinc-500" x-text="money(item.amount)"></span>
                            <button type="button" class="rounded-full p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-brand" x-on:click="unlink(item)" x-bind:aria-label="`Remove ${item.number}`">
                                <x-lucide-x class="h-3 w-3" />
                            </button>
                        </span>
                    </template>
                </div>
                <p x-show="errors.items" x-text="errors.items" x-cloak class="{{ $errorClasses }}"></p>

                {{-- Picker --}}
                <div>
                    <div class="relative">
                        <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                        <input type="text" class="nexora-input pl-9" autocomplete="off" placeholder="Search sale invoice no., job no. or customer…"
                               aria-label="Search sales and jobs" x-model="query" x-on:input="queueSearch()" x-on:keydown.enter.prevent="search()">
                    </div>
                    <ul class="mt-2 max-h-64 divide-y divide-zinc-100 overflow-y-auto rounded-md border border-zinc-200 text-sm">
                        <li x-show="searching" class="px-3 py-2 text-xs text-zinc-500">Searching…</li>
                        <li x-show="! searching && availableResults.length === 0" class="px-3 py-2 text-xs text-zinc-500">No unclaimed sales or jobs found.</li>
                        <template x-for="item in availableResults" :key="item.kind + item.id">
                            <li class="flex items-center justify-between gap-3 px-3 py-2 hover:bg-zinc-50">
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2">
                                        <span class="badge" x-bind:class="item.kind === 'job' ? 'badge-info' : 'badge-default'" x-text="item.kind === 'job' ? 'Job' : 'Sale'"></span>
                                        <span class="font-medium text-ink" x-text="item.number"></span>
                                    </span>
                                    <span class="block truncate text-xs text-zinc-500" x-text="item.description"></span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span class="tabular-nums text-zinc-600" x-text="money(item.amount)"></span>
                                    <button type="button" class="nexora-btn nexora-btn-outline !px-2.5 !py-1 text-xs" x-on:click="link(item)">
                                        <x-lucide-plus class="h-3.5 w-3.5" /> Link
                                    </button>
                                </span>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-1 text-xs text-zinc-400">Only sales and jobs no salary has paid commission on yet. Reversed bills are left out. Jobs count at their repair cost (or estimate).</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="issue-base" class="{{ $labelClasses }}">Commission base (Rs.)</label>
                        <input id="issue-base" type="number" min="0" step="0.01" class="nexora-input" placeholder="e.g. sales total for period"
                               x-model="form.commission_base" x-on:input="baseTouched = true">
                        <p class="mt-1 text-xs text-zinc-500" x-show="! baseTouched">Totals the linked sales / jobs.</p>
                        <button type="button" class="mt-1 text-xs text-brand hover:underline" x-show="baseTouched" x-on:click="resetBase()">
                            Use linked total (<span x-text="money(linkedTotal)"></span>)
                        </button>
                        <p x-show="errors.commission_base" x-text="errors.commission_base" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="issue-percent" class="{{ $labelClasses }}">Commission % *</label>
                        <input id="issue-percent" type="number" min="0" max="100" step="0.01" class="nexora-input" x-model="form.commission_percent">
                        <p class="mt-1 text-xs text-zinc-500">= <span x-text="money(commissionAmount)"></span></p>
                        <p x-show="errors.commission_percent" x-text="errors.commission_percent" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
            </div>

            {{-- Period + note --}}
            <div class="nexora-card grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <label for="issue-period" class="{{ $labelClasses }}">Period *</label>
                    <input id="issue-period" type="text" class="nexora-input" maxlength="100" required placeholder="e.g. August 2026" x-model="form.period_label">
                    <p x-show="errors.period_label" x-text="errors.period_label" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div>
                    <label for="issue-note" class="{{ $labelClasses }}">Note</label>
                    <input id="issue-note" type="text" class="nexora-input" maxlength="500" x-model="form.note">
                    <p x-show="errors.note" x-text="errors.note" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div class="sm:col-span-2 rounded-md border border-zinc-200 p-3">
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-brand focus:ring-brand" x-model="form.from_drawer">
                        Paid from cash drawer
                    </label>
                    <div x-show="form.from_drawer" x-cloak class="mt-3">
                        <template x-if="openShifts.length === 0">
                            <p class="text-sm text-amber-700">No shift is open right now.</p>
                        </template>
                        <template x-if="openShifts.length > 0">
                            <div>
                                <label for="issue-shift" class="{{ $labelClasses }}">Open shift *</label>
                                <select id="issue-shift" class="nexora-input" x-model="form.shift_id">
                                    <option value="">Choose a shift…</option>
                                    <template x-for="shift in openShifts" :key="shift.id">
                                        <option x-bind:value="shift.id" x-text="`${shift.label} (expected ${money(shift.expected_cash)})`"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-zinc-500">The amount comes off this shift's expected cash.</p>
                            </div>
                        </template>
                        <p x-show="errors.shift_id" x-text="errors.shift_id" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="lg:sticky lg:top-4 lg:self-start">
            <div class="nexora-card space-y-4 p-5">
                <h2 class="font-prata text-base text-ink">Amount to Pay</h2>
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between gap-3" x-show="hasMonthly">
                        <dt class="text-zinc-500">Monthly</dt>
                        <dd class="tabular-nums" x-text="money(form.monthly_amount)"></dd>
                    </div>
                    <div class="flex justify-between gap-3" x-show="hasCommission">
                        <dt class="text-zinc-500" x-text="`Commission (${money(commissionBase)} × ${Number(form.commission_percent || 0)}%)`"></dt>
                        <dd class="tabular-nums" x-text="money(commissionAmount)"></dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-zinc-200 pt-1 font-medium">
                        <dt>Calculated</dt>
                        <dd class="tabular-nums" x-text="money(calculatedAmount)"></dd>
                    </div>
                </dl>
                <div>
                    <label for="issue-amount" class="{{ $labelClasses }}">Amount to Pay (Rs.) *</label>
                    <input id="issue-amount" type="number" min="0.01" step="0.01" class="nexora-input font-prata text-lg" required
                           x-model="form.amount" x-on:input="amountTouched = true">
                    <button type="button" class="mt-1 text-xs text-brand hover:underline" x-show="amountTouched" x-on:click="resetAmount()">Use calculated amount</button>
                    <p x-show="errors.amount" x-text="errors.amount" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <p class="text-xs text-zinc-500" x-show="form.from_drawer && selectedShift">
                    Paid in cash from <span class="font-medium" x-text="selectedShift?.label"></span>.
                </p>
                <button type="submit" class="nexora-btn nexora-btn-primary w-full justify-center disabled:opacity-60" x-bind:disabled="saving || ! form.user_id">
                    <x-lucide-banknote class="h-4 w-4" />
                    <span x-text="saving ? 'Issuing…' : `Issue ${money(form.amount)}`"></span>
                </button>
                <p class="text-xs text-zinc-400">A salaries expense (EXP-) is recorded with the payment.</p>
            </div>
        </div>
    </form>
@endif
