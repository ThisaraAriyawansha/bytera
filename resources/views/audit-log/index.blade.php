@php
    use App\Models\AuditLog;

    $total = $logs->total();
    $hasFilters = $type !== null || $search !== '';
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="{ log: null }">
        <x-page-header title="Audit Log" :subtitle="'Every admin edit, with before and after values · '.number_format($total).' '.str('change')->plural($total).' in this period'" />

        <form method="GET" action="{{ route('audit-log.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
            <x-search-input placeholder="Search record no. or staff…" :value="$search" aria-label="Search the audit log" class="lg:max-w-sm lg:flex-1" />
            <label class="lg:w-48">
                <span class="mb-1 block text-xs font-medium text-zinc-600">Record type</span>
                <select name="type" class="nexora-input" onchange="this.form.requestSubmit()">
                    <option value="">All records</option>
                    @foreach (AuditLog::RECORD_TYPES as $value => $label)
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
                            <th class="px-5 py-3">When</th>
                            <th class="px-5 py-3">Who</th>
                            <th class="px-5 py-3">Record</th>
                            <th class="px-5 py-3">Fields Changed</th>
                            <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse ($logs as $log)
                            @php
                                $changes = collect($log->changes ?? []);
                                $payload = [
                                    'record' => $log->recordTypeLabel().' · '.$log->label,
                                    'when' => $log->created_at->format('M j, Y g:i A'),
                                    'who' => $log->performed_by_name,
                                    'changes' => $changes->map(fn (array $change): array => [
                                        'field' => AuditLog::fieldLabel((string) $change['field']),
                                        'before' => AuditLog::displayValue($change['before'] ?? null),
                                        'after' => AuditLog::displayValue($change['after'] ?? null),
                                    ])->all(),
                                ];
                            @endphp
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $log->created_at->format('M j, Y g:i A') }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-ink">{{ $log->performed_by_name }}</td>
                                <td class="whitespace-nowrap px-5 py-3">
                                    <span class="text-zinc-500">{{ $log->recordTypeLabel() }} ·</span>
                                    <span class="font-medium text-ink">{{ $log->label }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($changes as $change)
                                            <x-badge>{{ AuditLog::fieldLabel((string) $change['field']) }}</x-badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                            x-on:click="log = @js($payload); $dispatch('open-modal', 'audit-view')"
                                            title="View" aria-label="View changes to {{ $log->label }}">
                                        <x-lucide-eye class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-400">
                                    {{ $hasFilters ? 'No changes match these filters in this period.' : 'No admin edits in this period.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination :paginator="$logs" />
        </div>

        <x-modal name="audit-view" max-width="2xl">
            <x-slot:title>
                <span x-text="log?.record ?? 'Change'">Change</span>
            </x-slot:title>

            <template x-if="log">
                <div class="space-y-4">
                    <p class="text-sm text-zinc-500">
                        Changed by <span class="font-medium text-ink" x-text="log.who"></span> on <span x-text="log.when"></span>
                    </p>

                    <ul class="divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                        <template x-for="(change, index) in log.changes" :key="index">
                            <li class="px-4 py-3 text-sm">
                                <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-zinc-500" x-text="change.field"></p>
                                <div class="flex flex-col gap-1.5 sm:flex-row sm:items-start sm:gap-3">
                                    <span class="min-w-0 flex-1 whitespace-pre-line break-words rounded px-2 py-1"
                                          x-bind:class="change.before === null ? 'bg-zinc-50 italic text-zinc-400' : 'bg-red-50 text-red-700 line-through decoration-red-300'"
                                          x-text="change.before ?? '(empty)'"></span>
                                    <x-lucide-arrow-right class="hidden h-4 w-4 shrink-0 self-center text-zinc-400 sm:block" />
                                    <x-lucide-arrow-down class="h-4 w-4 shrink-0 text-zinc-400 sm:hidden" />
                                    <span class="min-w-0 flex-1 whitespace-pre-line break-words rounded px-2 py-1"
                                          x-bind:class="change.after === null ? 'bg-zinc-50 italic text-zinc-400' : 'bg-green-50 text-green-700'"
                                          x-text="change.after ?? '(empty)'"></span>
                                </div>
                            </li>
                        </template>
                    </ul>

                    <p class="text-xs text-zinc-400">The audit log is read-only — entries can't be edited or deleted.</p>
                </div>
            </template>
        </x-modal>
    </div>
</x-layouts.app>
