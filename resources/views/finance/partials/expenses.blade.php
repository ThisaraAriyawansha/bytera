@php
    use App\Models\Expense;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $total = $expenses->total();
@endphp

<div x-data="recordForm(@js([
        'modal' => 'expense-form',
        'storeUrl' => route('finance.expenses.store'),
        'blank' => ['category' => 'rent', 'amount' => '', 'note' => '', 'from_drawer' => false, 'shift_id' => ''],
     ]))">
    <form method="GET" action="{{ route('finance.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
        <input type="hidden" name="tab" value="expenses">
        <label class="lg:w-44">
            <span class="mb-1 block text-xs font-medium text-zinc-600">Category</span>
            <select name="category" class="nexora-input" onchange="this.form.requestSubmit()">
                <option value="">All categories</option>
                @foreach (Expense::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <x-date-range auto-submit class="lg:max-w-md lg:flex-1" />
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
            <a href="{{ route('finance.export', ['tab' => 'expenses', ...request()->only(['from', 'to', 'category'])]) }}" class="nexora-btn nexora-btn-outline justify-center">
                <x-lucide-download class="h-4 w-4" /> Export CSV
            </a>
            @if ($canAddExpense)
                <button type="button" class="nexora-btn nexora-btn-primary justify-center lg:ml-auto" x-on:click="openAdd()">
                    <x-lucide-plus class="h-4 w-4" /> Add Expense
                </button>
            @endif
        </div>
    </form>

    <p class="mb-2 text-sm text-zinc-500">
        {{ number_format($total) }} {{ str('expense')->plural($total) }} · <span class="font-medium text-ink">{{ Money::format($expensesTotal) }}</span>
    </p>

    <div class="nexora-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="whitespace-nowrap px-5 py-3">Expense No.</th>
                        <th class="px-5 py-3">Category</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3">Note</th>
                        <th class="whitespace-nowrap px-5 py-3">Paid By</th>
                        <th class="px-5 py-3">Drawer</th>
                        <th class="px-5 py-3">Date</th>
                        @if ($canDeleteExpense)
                            <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($expenses as $expense)
                        <tr class="hover:bg-zinc-50">
                            <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $expense->expense_no }}</td>
                            <td class="whitespace-nowrap px-5 py-3">
                                <x-badge :variant="$expense->category === 'salaries' ? 'info' : 'default'">{{ $expense->categoryLabel() }}</x-badge>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums text-ink">{{ Money::format($expense->amount) }}</td>
                            <td class="max-w-xs px-5 py-3 text-zinc-600">{{ $expense->note ?: '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $expense->paid_by_name }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $expense->shift_no ?: '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $expense->created_at->format('M j, Y g:i A') }}</td>
                            @if ($canDeleteExpense)
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    @if ($expense->linked_salary_payment_id === null)
                                        <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                x-on:click="$dispatch('open-modal', {
                                                    name: 'expense-delete',
                                                    message: @js("Delete {$expense->expense_no} (".Money::format($expense->amount).')?'.($expense->shift_no ? " If {$expense->shift_no} is still open, the cash goes back into its drawer." : '')." This can't be undone."),
                                                    action: @js(route('finance.expenses.destroy', $expense)),
                                                })"
                                                title="Delete" aria-label="Delete {{ $expense->expense_no }}">
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                        </button>
                                    @else
                                        <span class="text-xs text-zinc-400" title="Delete the salary payment from the Salary page">{{ $expense->linkedSalaryPayment?->payment_no }}</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-sm text-zinc-400">No expenses in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$expenses" />
    </div>

    @if ($canDeleteExpense)
        <x-confirm-dialog name="expense-delete" title="Delete expense?" confirm-label="Delete" />
    @endif

    @if ($canAddExpense)
        <x-modal name="expense-form" title="Add Expense">
            <form id="expense-form" class="space-y-4" x-on:submit.prevent="save()">
                <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="expense-category" class="{{ $labelClasses }}">Category *</label>
                        <select id="expense-category" class="nexora-input" x-model="form.category" autofocus>
                            @foreach (Expense::manualCategories() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p x-show="errors.category" x-text="errors.category" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="expense-amount" class="{{ $labelClasses }}">Amount *</label>
                        <input id="expense-amount" type="number" min="0.01" step="0.01" class="nexora-input" required x-model="form.amount">
                        <p x-show="errors.amount" x-text="errors.amount" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
                <div>
                    <label for="expense-note" class="{{ $labelClasses }}">Note</label>
                    <input id="expense-note" type="text" class="nexora-input" maxlength="500" placeholder="e.g. July shop rent" x-model="form.note">
                    <p x-show="errors.note" x-text="errors.note" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div class="rounded-md border border-zinc-200 p-3">
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" class="h-4 w-4 rounded border-zinc-300 text-brand focus:ring-brand" x-model="form.from_drawer">
                        Paid from cash drawer
                    </label>
                    <div x-show="form.from_drawer" x-cloak class="mt-3">
                        @if ($openShifts === [])
                            <p class="text-sm text-amber-700">No shift is open right now.</p>
                        @else
                            <label for="expense-shift" class="{{ $labelClasses }}">Open shift *</label>
                            <select id="expense-shift" class="nexora-input" x-model="form.shift_id">
                                <option value="">Choose a shift…</option>
                                @foreach ($openShifts as $shift)
                                    <option value="{{ $shift['id'] }}">{{ $shift['label'] }} (expected {{ Money::format($shift['expected_cash']) }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-zinc-500">The amount comes off this shift's expected cash.</p>
                        @endif
                        <p x-show="errors.shift_id" x-text="errors.shift_id" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
                <p class="text-xs text-zinc-500">Salaries are recorded from the Salary page.</p>
            </form>

            <x-slot:footer>
                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'expense-form')">Cancel</button>
                <button type="submit" form="expense-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                    <span x-text="saving ? 'Saving…' : 'Add Expense'"></span>
                </button>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
