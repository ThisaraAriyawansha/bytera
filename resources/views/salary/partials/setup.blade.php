@php
    use App\Models\SalaryPayment;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
@endphp

<div x-data="recordForm(@js([
        'modal' => 'salary-setup',
        'storeUrl' => null,
        'blank' => ['name' => '', 'salary_type' => '', 'salary_monthly_amount' => '', 'salary_commission_percent' => ''],
     ]))">
    <div class="nexora-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                        <th class="px-5 py-3">Employee</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Salary Setup</th>
                        @if ($canManageConfig)
                            <th class="px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($employees as $employee)
                        <tr class="hover:bg-zinc-50">
                            <td class="px-5 py-3">
                                <div class="font-medium text-ink">{{ $employee->name ?: $employee->email }}</div>
                                <div class="text-xs text-zinc-400">{{ $employee->email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3"><x-badge>{{ $employee->role }}</x-badge></td>
                            <td @class(['whitespace-nowrap px-5 py-3', 'text-ink' => $employee->salary_type !== null, 'text-zinc-400' => $employee->salary_type === null])>
                                {{ $employee->salarySetupLabel() }}
                            </td>
                            @if ($canManageConfig)
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="openEdit(@js([
                                                'name' => $employee->name ?: $employee->email,
                                                'salary_type' => $employee->salary_type ?? '',
                                                'salary_monthly_amount' => $employee->salary_monthly_amount === null ? '' : (float) $employee->salary_monthly_amount,
                                                'salary_commission_percent' => $employee->salary_commission_percent === null ? '' : (float) $employee->salary_commission_percent,
                                                'updateUrl' => route('salary.setup.update', $employee),
                                            ]))"
                                            title="Edit" aria-label="Edit salary setup for {{ $employee->name }}">
                                        <x-lucide-pencil class="h-4 w-4" />
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-zinc-400">No employees yet. Add them under Settings → Team.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-2 text-xs text-zinc-400">This is only a pre-fill default — every payment can still be changed when it's issued.</p>

    @if ($canManageConfig)
        <x-modal name="salary-setup">
            <x-slot:title><span x-text="`Salary Setup — ${form.name}`">Salary Setup</span></x-slot:title>

            <form id="salary-setup-form" class="space-y-4" x-on:submit.prevent="save()">
                <p x-show="errors.form" x-text="errors.form" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>
                <div>
                    <span class="{{ $labelClasses }}">Type</span>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (SalaryPayment::TYPES as $key => $label)
                            <button type="button" class="rounded-md border px-3 py-2 text-sm transition-colors"
                                    x-bind:class="form.salary_type === @js($key) ? 'border-ink bg-ink text-white' : 'border-zinc-200 text-ink hover:border-brand hover:text-brand'"
                                    x-on:click="form.salary_type = @js($key)">{{ $label }}</button>
                        @endforeach
                    </div>
                    <p x-show="! form.salary_type" class="mt-1 text-xs text-zinc-500">Not configured.</p>
                    <p x-show="errors.salary_type" x-text="errors.salary_type" x-cloak class="{{ $errorClasses }}"></p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div x-show="form.salary_type === 'monthly' || form.salary_type === 'hybrid'">
                        <label for="setup-monthly" class="{{ $labelClasses }}">Monthly amount (Rs.)</label>
                        <input id="setup-monthly" type="number" min="0" step="0.01" class="nexora-input" x-model="form.salary_monthly_amount">
                        <p x-show="errors.salary_monthly_amount" x-text="errors.salary_monthly_amount" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                    <div x-show="form.salary_type === 'commission' || form.salary_type === 'hybrid'">
                        <label for="setup-percent" class="{{ $labelClasses }}">Commission %</label>
                        <input id="setup-percent" type="number" min="0" max="100" step="0.01" class="nexora-input" x-model="form.salary_commission_percent">
                        <p x-show="errors.salary_commission_percent" x-text="errors.salary_commission_percent" x-cloak class="{{ $errorClasses }}"></p>
                    </div>
                </div>
            </form>

            <x-slot:footer class="!justify-between">
                <button type="button" class="nexora-btn nexora-btn-ghost disabled:opacity-60" x-bind:disabled="saving"
                        x-on:click="form.salary_type = ''; form.salary_monthly_amount = ''; form.salary_commission_percent = ''; save()">
                    Clear
                </button>
                <div class="flex gap-2">
                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="$dispatch('close-modal', 'salary-setup')">Cancel</button>
                    <button type="submit" form="salary-setup-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="saving">
                        <span x-text="saving ? 'Saving…' : 'Save'"></span>
                    </button>
                </div>
            </x-slot:footer>
        </x-modal>
    @endif
</div>
