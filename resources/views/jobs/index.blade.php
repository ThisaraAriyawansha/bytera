@php
    use App\Models\Job;
    use App\Support\Money;

    $labelClasses = 'mb-1 block text-xs font-medium text-zinc-600';
    $errorClasses = 'mt-1 text-xs text-red-600';
    $sectionClasses = 'mb-3 font-prata text-sm text-ink';
    $total = $jobs->total();
    $allCount = $statusCounts->sum();
    $hasFilters = $filters['status'] !== null || $filters['from'] !== null || $filters['to'] !== null || $filters['search'] !== '';
    $chipUrl = fn (?string $status): string => route('jobs.index', array_filter([...request()->except(['status', 'page']), 'status' => $status], fn ($value) => filled($value)));
@endphp

<x-layouts.app>
    <div class="p-4 sm:p-8" x-data="jobForm(@js($jobConfig))">
        <div x-data="jobView(@js(['statuses' => Job::STATUSES]))"
             x-on:job-show.window="show($event.detail.url, $event.detail)"
             x-on:job-loaded.window="edited($event.detail)">

            <x-page-header title="Jobs" :subtitle="number_format($total).' '.str('job')->plural($total).($hasFilters ? ' found' : '').' · repair job notes, status updates and history'">
                <x-slot:actions>
                    <a href="{{ route('jobs.export', request()->except('page')) }}" class="nexora-btn nexora-btn-outline">
                        <x-lucide-download class="h-4 w-4" /> Export Report
                    </a>
                    <button type="button" class="nexora-btn nexora-btn-primary" x-on:click="openNew()">
                        <x-lucide-plus class="h-4 w-4" /> New Job
                    </button>
                </x-slot:actions>
            </x-page-header>

            <x-flash />

            {{-- Status summary chips --}}
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ $chipUrl(null) }}"
                   @class(['inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                           'border-ink bg-ink text-white' => $filters['status'] === null,
                           'border-zinc-200 bg-white text-zinc-600 hover:border-brand hover:text-brand' => $filters['status'] !== null])>
                    All <span class="tabular-nums opacity-70">{{ number_format($allCount) }}</span>
                </a>
                @foreach (Job::STATUSES as $status => $meta)
                    <a href="{{ $chipUrl($status) }}"
                       @class(['inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
                               'border-ink bg-ink text-white' => $filters['status'] === $status,
                               'border-zinc-200 bg-white text-zinc-600 hover:border-brand hover:text-brand' => $filters['status'] !== $status])>
                        <span @class(['h-1.5 w-1.5 rounded-full',
                                      'bg-amber-500' => $meta['variant'] === 'warning',
                                      'bg-zinc-400' => $meta['variant'] === 'default',
                                      'bg-green-500' => $meta['variant'] === 'success',
                                      'bg-blue-500' => $meta['variant'] === 'info',
                                      'bg-red-500' => $meta['variant'] === 'danger'])></span>
                        {{ $meta['label'] }} <span class="tabular-nums opacity-70">{{ number_format($statusCounts[$status] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('jobs.index') }}" class="mb-4 flex flex-col gap-2 lg:flex-row lg:items-end">
                <x-search-input placeholder="Search job no., customer or phone…" :value="$filters['search']" aria-label="Search jobs" class="lg:max-w-sm lg:flex-1" />
                <label class="lg:w-44">
                    <span class="mb-1 block text-xs font-medium text-zinc-600">Status</span>
                    <select name="status" class="nexora-input" onchange="this.form.requestSubmit()">
                        <option value="">All statuses</option>
                        @foreach (Job::STATUSES as $status => $meta)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="min-w-[9rem] flex-1">
                        <span class="mb-1 block text-xs font-medium text-zinc-600">From</span>
                        <input type="date" name="from" class="nexora-input" value="{{ $filters['from'] }}" onchange="this.form.requestSubmit()">
                    </label>
                    <label class="min-w-[9rem] flex-1">
                        <span class="mb-1 block text-xs font-medium text-zinc-600">To</span>
                        <input type="date" name="to" class="nexora-input" value="{{ $filters['to'] }}" onchange="this.form.requestSubmit()">
                    </label>
                </div>
                <button type="submit" class="nexora-btn nexora-btn-outline justify-center">Filter</button>
                @if ($hasFilters)
                    <a href="{{ route('jobs.index') }}" class="nexora-btn nexora-btn-ghost justify-center">Clear</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="nexora-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="whitespace-nowrap px-5 py-3">Job No</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Device</th>
                                <th class="px-5 py-3">Received</th>
                                <th class="whitespace-nowrap px-5 py-3 text-right">Est. Cost</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="relative px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @forelse ($jobs as $job)
                                @php($status = Job::STATUSES[$job->status] ?? Job::STATUSES['pending'])
                                <tr class="hover:bg-zinc-50">
                                    <td class="whitespace-nowrap px-5 py-3 font-medium text-ink">{{ $job->job_no }}</td>
                                    <td class="px-5 py-3">
                                        <div class="text-ink">{{ $job->customer_name }}</div>
                                        <div class="text-xs text-zinc-400">{{ $job->customer_phone }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-zinc-600">{{ $job->deviceLabel() }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-zinc-600">{{ $job->created_at->format('M j, Y') }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right tabular-nums">{{ Money::format($job->estimated_cost) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3"><x-badge :variant="$status['variant']" dot>{{ $status['label'] }}</x-badge></td>
                                    <td class="whitespace-nowrap px-5 py-3 text-right">
                                        <button type="button" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 hover:text-brand"
                                                x-on:click="show(@js(route('jobs.show', $job)))"
                                                title="View" aria-label="View {{ $job->job_no }}">
                                            <x-lucide-eye class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-sm text-zinc-400">
                                        {{ $hasFilters ? 'No jobs match these filters.' : 'No jobs yet. Tap New Job to take one in.' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-pagination :paginator="$jobs" />
            </div>

            {{-- ═══ Job view ═══ --}}
            <x-modal name="job-view" max-width="4xl" x-model="viewOpen">
                <x-slot:title>
                    <span class="inline-flex flex-wrap items-center gap-2">
                        <span x-text="job?.job_no ?? 'Job'">Job</span>
                        <span x-show="job" class="badge gap-1.5 font-poppins" x-bind:class="statusBadge(job?.status)">
                            <span class="h-1.5 w-1.5 rounded-full" x-bind:class="statusDot(job?.status)"></span>
                            <span x-text="statusLabel(job?.status)"></span>
                        </span>
                    </span>
                </x-slot:title>

                <div class="space-y-5">
                    <p x-show="viewLoading && ! job" class="py-8 text-center text-sm text-zinc-400">Loading…</p>
                    <p x-show="viewNotice" x-text="viewNotice" x-cloak class="no-print rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700"></p>
                    <p x-show="viewErrors.form || viewErrors.email" x-text="viewErrors.form || viewErrors.email" x-cloak class="no-print rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"></p>

                    {{-- Email prompt --}}
                    <div x-show="emailPrompt" x-cloak class="no-print flex flex-col gap-3 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 sm:flex-row sm:items-center sm:justify-between">
                        <span class="flex items-center gap-2">
                            <x-lucide-mail class="h-4 w-4 shrink-0" />
                            <span x-text="emailPrompt === 'received' ? 'Send this job confirmation by email?' : 'Email this update to the customer?'"></span>
                            <span class="text-xs text-blue-600" x-text="job?.customer_email"></span>
                        </span>
                        <span class="flex shrink-0 gap-2">
                            <button type="button" class="nexora-btn nexora-btn-ghost !py-1.5" x-on:click="emailPrompt = null">Not now</button>
                            <button type="button" class="nexora-btn nexora-btn-primary !py-1.5 disabled:opacity-60" x-bind:disabled="emailSending" x-on:click="sendEmail()">
                                <x-lucide-send class="h-4 w-4" /> <span x-text="emailSending ? 'Sending…' : 'Send Email'"></span>
                            </button>
                        </span>
                    </div>

                    <template x-if="job">
                        <div class="space-y-5">
                            {{-- Actions --}}
                            <div class="no-print flex flex-wrap gap-2">
                                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="printJob()">
                                    <x-lucide-printer class="h-4 w-4" /> Print A4
                                </button>
                                <button type="button" class="nexora-btn nexora-btn-outline disabled:opacity-60" x-bind:disabled="pdfBusy" x-on:click="downloadJob()">
                                    <x-lucide-download class="h-4 w-4" /> <span x-text="pdfBusy ? 'Preparing…' : 'Download'"></span>
                                </button>
                                @if ($canEdit)
                                    <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="openEdit(job)">
                                        <x-lucide-pencil class="h-4 w-4" /> Edit Job
                                    </button>
                                @endif
                                <button type="button" class="nexora-btn nexora-btn-ghost" x-show="job.customer_email && ! emailPrompt" x-on:click="emailPrompt = 'received'">
                                    <x-lucide-mail class="h-4 w-4" /> Email job note
                                </button>
                            </div>

                            {{-- Details --}}
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                    <h4 class="{{ $sectionClasses }}">Customer</h4>
                                    <p class="font-medium text-ink" x-text="job.customer_name"></p>
                                    <p class="text-zinc-600" x-show="job.customer_company" x-text="job.customer_company"></p>
                                    <p class="text-zinc-600" x-text="[job.customer_phone, job.customer_phone2].filter(Boolean).join(' / ')"></p>
                                    <p class="break-all text-zinc-600" x-show="job.customer_email" x-text="job.customer_email"></p>
                                    <p class="text-zinc-600" x-show="job.customer_address || job.customer_city" x-text="[job.customer_address, job.customer_city].filter(Boolean).join(', ')"></p>
                                </div>
                                <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                    <h4 class="{{ $sectionClasses }}">Device</h4>
                                    <p class="font-medium text-ink" x-text="job.device_type === 'Other' ? (job.device_type_other || 'Other') : job.device_type"></p>
                                    <p class="text-zinc-600" x-show="job.brand || job.model" x-text="[job.brand, job.model].filter(Boolean).join(' ')"></p>
                                    <p class="text-zinc-600" x-show="job.serial_no">Serial: <span x-text="job.serial_no"></span></p>
                                    <p class="text-zinc-600" x-show="job.color">Colour: <span x-text="job.color"></span></p>
                                </div>
                                <div class="rounded-lg border border-zinc-200 p-4 text-sm">
                                    <h4 class="{{ $sectionClasses }}">Job</h4>
                                    <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                                        <dt class="text-zinc-500">Received</dt><dd class="text-ink" x-text="job.received_at"></dd>
                                        <dt class="text-zinc-500">By</dt><dd class="text-ink" x-text="job.received_by_name"></dd>
                                        <dt class="text-zinc-500">Technician</dt><dd class="text-ink" x-text="job.assigned_technician_name || '—'"></dd>
                                        <dt class="text-zinc-500">Expected</dt><dd class="text-ink" x-text="job.expected_delivery_label || '—'"></dd>
                                        <dt class="text-zinc-500" x-show="job.date_returned">Returned</dt><dd class="text-ink" x-show="job.date_returned" x-text="job.date_returned"></dd>
                                    </dl>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-3 text-sm">
                                    <div>
                                        <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Fault</h4>
                                        <p class="whitespace-pre-line text-ink" x-text="job.fault_description"></p>
                                    </div>
                                    <div>
                                        <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Accessories</h4>
                                        <p class="text-ink" x-text="[...job.accessories, job.accessories_other].filter(Boolean).join(', ') || 'None'"></p>
                                    </div>
                                    <div>
                                        <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Physical condition</h4>
                                        <p class="text-ink" x-text="job.physical_condition.join(', ') || '—'"></p>
                                    </div>
                                    <div x-show="job.special_notes">
                                        <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Special notes</h4>
                                        <p class="whitespace-pre-line text-ink" x-text="job.special_notes"></p>
                                    </div>
                                    <div x-show="job.parts.length > 0">
                                        <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Device parts</h4>
                                        <ul class="divide-y divide-zinc-100 rounded-md border border-zinc-200">
                                            <template x-for="part in job.parts" :key="part.id">
                                                <li class="px-3 py-1.5">
                                                    <span class="font-medium text-ink" x-text="part.name || '—'"></span>
                                                    <span class="text-zinc-600" x-show="part.spec" x-text="' · ' + part.spec"></span>
                                                    <span class="text-xs text-zinc-400" x-show="part.serialNo" x-text="' · S/N ' + part.serialNo"></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>

                                <div class="text-sm">
                                    <h4 class="mb-1 text-xs font-medium uppercase tracking-wider text-zinc-500">Services &amp; charges</h4>
                                    <ul class="divide-y divide-zinc-100 rounded-md border border-zinc-200" x-show="job.services.length > 0">
                                        <template x-for="service in job.services" :key="service.id">
                                            <li class="flex items-start justify-between gap-3 px-3 py-1.5">
                                                <span>
                                                    <span class="text-ink" x-text="service.name"></span>
                                                    <span class="block text-xs text-zinc-400" x-show="service.chargeType === 'free' && service.freeReason" x-text="service.freeReason"></span>
                                                </span>
                                                <span class="whitespace-nowrap tabular-nums" x-bind:class="service.chargeType === 'free' ? 'text-green-700' : 'text-ink'"
                                                      x-text="service.chargeType === 'free' ? 'Free' : money(service.price)"></span>
                                            </li>
                                        </template>
                                    </ul>
                                    <p x-show="job.services.length === 0" class="text-zinc-400">No services added.</p>
                                    <dl class="mt-3 space-y-1">
                                        <div class="flex justify-between" x-show="job.services.length > 0"><dt class="text-zinc-500">Services total</dt><dd class="tabular-nums" x-text="money(job.services_total)"></dd></div>
                                        <div class="flex justify-between"><dt class="text-zinc-500">Estimated cost</dt><dd class="tabular-nums" x-text="money(job.estimated_cost)"></dd></div>
                                        <div class="flex justify-between" x-show="job.repair_cost !== null"><dt class="text-zinc-500">Repair cost</dt><dd class="tabular-nums" x-text="money(job.repair_cost)"></dd></div>
                                        <div class="flex justify-between"><dt class="text-zinc-500">Advance paid</dt><dd class="tabular-nums text-green-700" x-text="money(job.advance_paid)"></dd></div>
                                        <div class="flex justify-between border-t border-zinc-200 pt-1 font-medium"><dt>Balance</dt><dd class="font-prata tabular-nums" x-text="money(job.balance)"></dd></div>
                                    </dl>
                                </div>
                            </div>

                            {{-- Update Job Status --}}
                            <form class="no-print rounded-lg border border-zinc-200 bg-zinc-50 p-4" x-on:submit.prevent="saveStatus()">
                                <h4 class="{{ $sectionClasses }}">Update Job Status</h4>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="job-status" class="{{ $labelClasses }}">Status</label>
                                        <select id="job-status" class="nexora-input" x-model="statusForm.status">
                                            @foreach (Job::STATUSES as $status => $meta)
                                                <option value="{{ $status }}">{{ $meta['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <p x-show="viewErrors.status" x-text="viewErrors.status" class="{{ $errorClasses }}"></p>
                                    </div>
                                    <div x-show="statusForm.status === 'done'">
                                        <label for="job-repair-cost" class="{{ $labelClasses }}">Repair cost / price (Rs.)</label>
                                        <div class="flex gap-2">
                                            <input id="job-repair-cost" type="number" min="0" step="0.01" class="nexora-input" placeholder="0" x-model="statusForm.repair_cost">
                                            <button type="button" class="nexora-btn nexora-btn-outline shrink-0 whitespace-nowrap !px-3" x-on:click="useServicesTotal()">Use services total</button>
                                        </div>
                                        <p x-show="viewErrors.repair_cost" x-text="viewErrors.repair_cost" class="{{ $errorClasses }}"></p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="job-status-note" class="{{ $labelClasses }}">Note</label>
                                        <textarea id="job-status-note" rows="2" class="nexora-input" maxlength="1000"
                                                  placeholder="Note about this update (what was done / changed)…" x-model="statusForm.note"></textarea>
                                        <p x-show="viewErrors.note" x-text="viewErrors.note" class="{{ $errorClasses }}"></p>
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="statusSaving">
                                        <x-lucide-save class="h-4 w-4" /> <span x-text="statusSaving ? 'Saving…' : 'Save Update'"></span>
                                    </button>
                                </div>
                            </form>

                            {{-- Job History --}}
                            <div class="no-print">
                                <h4 class="{{ $sectionClasses }}">Job History</h4>
                                <ol class="relative ml-2 space-y-4 border-l border-zinc-200 pl-5">
                                    <template x-for="entry in job.history" :key="entry.id">
                                        <li class="relative">
                                            <span class="absolute -left-[1.6rem] top-1.5 h-2.5 w-2.5 rounded-full ring-4 ring-white" x-bind:class="statusDot(entry.status)"></span>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="badge" x-bind:class="statusBadge(entry.status)" x-text="entry.status_label"></span>
                                                <span class="text-xs text-zinc-400" x-text="`${entry.date} · ${entry.updated_by_name}`"></span>
                                            </div>
                                            <p class="mt-1 text-sm text-ink" x-show="entry.note" x-text="entry.note"></p>
                                            <p class="text-xs text-zinc-500" x-show="entry.repair_cost !== null">Repair cost: <span class="tabular-nums" x-text="money(entry.repair_cost)"></span></p>
                                        </li>
                                    </template>
                                </ol>
                            </div>

                            {{-- A4 job note (printed / downloaded) --}}
                            <div>
                                <h4 class="no-print {{ $sectionClasses }}">Job Note (A4)</h4>
                                <div class="overflow-x-auto rounded-md border border-zinc-200 bg-zinc-100 p-2">
                                    <div class="mx-auto w-[210mm] shadow-sm" x-html="printHtml"></div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </x-modal>
        </div>

        {{-- ═══ New Job Note / Edit Job ═══ --}}
        <x-modal name="job-form" max-width="4xl" x-model="formOpen">
            <x-slot:title><span x-text="isEditing ? `Edit Job ${editJobNo}` : 'New Job Note'">New Job Note</span></x-slot:title>

            <form id="job-form" class="space-y-6" x-on:submit.prevent="saveJob()">
                <div x-show="errorList.length > 0" x-cloak class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
                    <template x-for="message in errorList" :key="message"><p x-text="message"></p></template>
                </div>

                {{-- Job no --}}
                <div x-show="! isEditing" class="max-w-xs">
                    <label for="job-no" class="{{ $labelClasses }}">Job No</label>
                    <input id="job-no" type="text" class="nexora-input font-medium uppercase" maxlength="30" autocomplete="off" x-model="form.job_no">
                    <p class="mt-1 text-xs text-zinc-400">Next number shown — type over it for a custom number.</p>
                    <p x-show="fieldError('job_no')" x-text="fieldError('job_no')" class="{{ $errorClasses }}"></p>
                </div>

                {{-- Customer --}}
                <section>
                    <h4 class="{{ $sectionClasses }}">Customer</h4>
                    <div class="mb-3">
                        <div x-show="form.customer_id" x-cloak class="flex items-center justify-between gap-3 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                            <span class="flex items-center gap-2"><x-lucide-user-check class="h-4 w-4" /> Existing customer: <span class="font-medium" x-text="form.customer_name"></span></span>
                            <button type="button" class="text-xs font-medium hover:underline" x-on:click="clearCustomer()">Change</button>
                        </div>
                        <div x-show="! form.customer_id">
                            <div class="relative">
                                <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400" />
                                <input type="search" class="nexora-input pl-9" placeholder="Search existing customer by name or phone…" autocomplete="off" x-model="customerQuery" aria-label="Search customers">
                                <div x-show="customerResults.length > 0 || customerSearching" x-cloak class="absolute left-0 top-full z-30 mt-1 w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-lg">
                                    <p x-show="customerSearching" class="px-3 py-2 text-xs text-zinc-500">Searching…</p>
                                    <template x-for="result in customerResults" :key="result.id">
                                        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-brand-light" x-on:click="pickCustomer(result)">
                                            <span class="font-medium text-ink" x-text="result.name"></span>
                                            <span class="text-xs text-zinc-500" x-text="result.phone"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-zinc-400">Or type a new customer below — they're added to Customers when the job is saved.</p>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="job-customer-name" class="{{ $labelClasses }}">Name *</label>
                            <input id="job-customer-name" type="text" class="nexora-input" maxlength="100" x-model="form.customer_name">
                            <p x-show="fieldError('customer_name')" x-text="fieldError('customer_name')" class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="job-customer-company" class="{{ $labelClasses }}">Company</label>
                            <input id="job-customer-company" type="text" class="nexora-input" maxlength="150" x-model="form.customer_company">
                        </div>
                        <div>
                            <label for="job-customer-phone" class="{{ $labelClasses }}">Mobile *</label>
                            <input id="job-customer-phone" type="tel" class="nexora-input" maxlength="30" x-model="form.customer_phone">
                            <p x-show="fieldError('customer_phone')" x-text="fieldError('customer_phone')" class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="job-customer-phone2" class="{{ $labelClasses }}">Mobile 2</label>
                            <input id="job-customer-phone2" type="tel" class="nexora-input" maxlength="30" x-model="form.customer_phone2">
                        </div>
                        <div>
                            <label for="job-customer-email" class="{{ $labelClasses }}">Email</label>
                            <input id="job-customer-email" type="email" class="nexora-input" x-model="form.customer_email">
                            <p x-show="fieldError('customer_email')" x-text="fieldError('customer_email')" class="{{ $errorClasses }}"></p>
                        </div>
                        <div>
                            <label for="job-customer-city" class="{{ $labelClasses }}">City</label>
                            <input id="job-customer-city" type="text" class="nexora-input" maxlength="100" x-model="form.customer_city">
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label for="job-customer-address" class="{{ $labelClasses }}">Address</label>
                            <input id="job-customer-address" type="text" class="nexora-input" maxlength="500" x-model="form.customer_address">
                        </div>
                    </div>
                </section>

                {{-- Device --}}
                <section>
                    <h4 class="{{ $sectionClasses }}">Device</h4>
                    <div class="mb-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Device type">
                        <template x-for="type in jobConfig.deviceTypes" :key="type">
                            <button type="button" role="radio" class="rounded-md border px-3 py-1.5 text-sm font-medium transition-colors"
                                    x-bind:aria-checked="form.device_type === type"
                                    x-bind:class="form.device_type === type ? 'border-ink bg-ink text-white' : 'border-zinc-200 text-zinc-600 hover:border-ink'"
                                    x-on:click="form.device_type = type" x-text="type"></button>
                        </template>
                    </div>
                    <div x-show="form.device_type === 'Other'" x-cloak class="mb-3 max-w-sm">
                        <label for="job-device-other" class="{{ $labelClasses }}">Specify device type *</label>
                        <input id="job-device-other" type="text" class="nexora-input" maxlength="100" placeholder="e.g. Tablet" x-model="form.device_type_other">
                        <p x-show="fieldError('device_type_other')" x-text="fieldError('device_type_other')" class="{{ $errorClasses }}"></p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="job-brand" class="{{ $labelClasses }}">Brand</label>
                            <input id="job-brand" type="text" class="nexora-input" maxlength="100" x-model="form.brand">
                        </div>
                        <div>
                            <label for="job-model" class="{{ $labelClasses }}">Model</label>
                            <input id="job-model" type="text" class="nexora-input" maxlength="100" x-model="form.model">
                        </div>
                        <div>
                            <label for="job-serial" class="{{ $labelClasses }}">Serial No</label>
                            <input id="job-serial" type="text" class="nexora-input" maxlength="100" x-model="form.serial_no">
                        </div>
                        <div>
                            <label for="job-color" class="{{ $labelClasses }}">Colour</label>
                            <input id="job-color" type="text" class="nexora-input" maxlength="50" x-model="form.color">
                        </div>
                    </div>
                </section>

                {{-- Device parts --}}
                <section>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="font-prata text-sm text-ink">Device Parts</h4>
                        <button type="button" class="nexora-btn nexora-btn-outline !py-1.5" x-on:click="addPart()">
                            <x-lucide-plus class="h-4 w-4" /> Add Part
                        </button>
                    </div>
                    <div class="mb-3 flex flex-wrap gap-1.5" x-show="partPresets.length > 0">
                        <template x-for="preset in partPresets" :key="preset">
                            <button type="button" class="rounded-full border border-dashed border-zinc-300 px-2.5 py-0.5 text-xs text-zinc-600 hover:border-brand hover:text-brand"
                                    x-on:click="addPart(preset)">+ <span x-text="preset"></span></button>
                        </template>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(part, index) in form.parts" :key="part.id">
                            <div class="grid gap-2 sm:grid-cols-[1fr_1.5fr_1fr_auto]">
                                <input type="text" class="nexora-input" maxlength="100" placeholder="e.g. RAM" x-model="part.name" aria-label="Part">
                                <input type="text" class="nexora-input" maxlength="200" placeholder="e.g. 8GB DDR4 Kingston" x-model="part.spec" aria-label="Spec">
                                <input type="text" class="nexora-input" maxlength="100" placeholder="Serial No" x-model="part.serialNo" aria-label="Serial No">
                                <button type="button" class="justify-self-end rounded p-2 text-zinc-400 hover:bg-zinc-100 hover:text-brand" x-on:click="removePart(part)" aria-label="Remove part">
                                    <x-lucide-trash-2 class="h-4 w-4" />
                                </button>
                            </div>
                        </template>
                        <p x-show="form.parts.length === 0" class="text-xs text-zinc-400">No parts recorded. Use the chips above or Add Part.</p>
                    </div>
                </section>

                {{-- Fault --}}
                <section>
                    <label for="job-fault" class="{{ $sectionClasses }} block">Fault description *</label>
                    <textarea id="job-fault" rows="3" class="nexora-input" maxlength="2000" placeholder="What's wrong with the device?" x-model="form.fault_description"></textarea>
                    <p x-show="fieldError('fault_description')" x-text="fieldError('fault_description')" class="{{ $errorClasses }}"></p>
                </section>

                {{-- Accessories & condition --}}
                <section class="grid gap-6 md:grid-cols-2">
                    <div>
                        <h4 class="{{ $sectionClasses }}">Accessories</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="accessory in jobConfig.accessories" :key="accessory">
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" class="accent-brand" x-bind:checked="form.accessories.includes(accessory)" x-on:change="toggle('accessories', accessory)">
                                    <span x-text="accessory"></span>
                                </label>
                            </template>
                        </div>
                        <input type="text" class="nexora-input mt-3" maxlength="300" placeholder="Other accessories" x-model="form.accessories_other" aria-label="Other accessories">
                    </div>
                    <div>
                        <h4 class="{{ $sectionClasses }}">Physical condition</h4>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="condition in jobConfig.conditions" :key="condition">
                                <label class="flex items-center gap-2 text-sm text-ink">
                                    <input type="checkbox" class="accent-brand" x-bind:checked="form.physical_condition.includes(condition)" x-on:change="toggle('physical_condition', condition)">
                                    <span x-text="condition"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="job-notes" class="{{ $labelClasses }}">Special notes</label>
                        <textarea id="job-notes" rows="2" class="nexora-input" maxlength="2000" x-model="form.special_notes"></textarea>
                    </div>
                    <div>
                        <label for="job-technician" class="{{ $labelClasses }}">Assign technician</label>
                        <select id="job-technician" class="nexora-input" x-model="form.assigned_technician_id">
                            <option value="">Not assigned</option>
                            <template x-for="technician in jobConfig.technicians" :key="technician.id">
                                <option :value="technician.id" x-text="technician.name" :selected="String(technician.id) === String(form.assigned_technician_id)"></option>
                            </template>
                        </select>
                        <p x-show="fieldError('assigned_technician_id')" x-text="fieldError('assigned_technician_id')" class="{{ $errorClasses }}"></p>
                    </div>
                </section>

                {{-- Services & charges --}}
                <section>
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h4 class="font-prata text-sm text-ink">Services &amp; Charges</h4>
                        <button type="button" class="nexora-btn nexora-btn-outline !py-1.5" x-on:click="addService()">
                            <x-lucide-plus class="h-4 w-4" /> Add Service
                        </button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(service, index) in form.services" :key="service.id">
                            <div>
                                <div class="grid gap-2 sm:grid-cols-[1.5fr_auto_1fr_auto]">
                                    <input type="text" class="nexora-input" maxlength="150" placeholder="Service, e.g. Replace SSD" x-model="service.name" aria-label="Service">
                                    <div class="inline-flex rounded-md border border-zinc-200 p-0.5">
                                        <button type="button" class="rounded px-3 py-1 text-xs font-medium"
                                                x-bind:class="service.chargeType === 'paid' ? 'bg-ink text-white' : 'text-zinc-600'" x-on:click="setChargeType(service, 'paid')">Paid</button>
                                        <button type="button" class="rounded px-3 py-1 text-xs font-medium"
                                                x-bind:class="service.chargeType === 'free' ? 'bg-green-600 text-white' : 'text-zinc-600'" x-on:click="setChargeType(service, 'free')">Free</button>
                                    </div>
                                    <input x-show="service.chargeType === 'paid'" type="number" min="0" step="0.01" class="nexora-input text-right" placeholder="Price" x-model="service.price" aria-label="Price">
                                    <input x-show="service.chargeType === 'free'" type="text" class="nexora-input" maxlength="200" placeholder="Reason (Warranty, Loyalty, Goodwill…)" x-model="service.freeReason" aria-label="Free reason">
                                    <button type="button" class="justify-self-end rounded p-2 text-zinc-400 hover:bg-zinc-100 hover:text-brand" x-on:click="removeService(service)" aria-label="Remove service">
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                    </button>
                                </div>
                                <p x-show="fieldError(`services.${index}.name`) || fieldError(`services.${index}.price`)"
                                   x-text="fieldError(`services.${index}.name`) || fieldError(`services.${index}.price`)" class="{{ $errorClasses }}"></p>
                            </div>
                        </template>
                        <p x-show="form.services.length === 0" class="text-xs text-zinc-400">No services yet — add the work to be done and its charge.</p>
                        <p x-show="form.services.length > 0" class="text-right text-sm text-zinc-600">Services total: <span class="font-medium tabular-nums text-ink" x-text="money(servicesTotalCents / 100)"></span></p>
                    </div>
                </section>

                {{-- Money & dates --}}
                <section class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="job-estimated" class="{{ $labelClasses }}">Estimated cost</label>
                        <input id="job-estimated" type="number" min="0" step="0.01" class="nexora-input" placeholder="0" x-model="form.estimated_cost">
                        <p x-show="fieldError('estimated_cost')" x-text="fieldError('estimated_cost')" class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="job-advance" class="{{ $labelClasses }}">Advance paid</label>
                        <input id="job-advance" type="number" min="0" step="0.01" class="nexora-input" placeholder="0" x-model="form.advance_paid">
                        <p x-show="fieldError('advance_paid')" x-text="fieldError('advance_paid')" class="{{ $errorClasses }}"></p>
                    </div>
                    <div>
                        <label for="job-expected" class="{{ $labelClasses }}">Expected delivery date</label>
                        <input id="job-expected" type="date" class="nexora-input" x-model="form.expected_delivery_date">
                        <p x-show="fieldError('expected_delivery_date')" x-text="fieldError('expected_delivery_date')" class="{{ $errorClasses }}"></p>
                    </div>
                </section>

                <p class="text-xs text-zinc-500" x-show="isEditing">Job no, status, repair cost, received-by and dates can't be edited here. Changes are recorded in the audit log.</p>
            </form>

            <x-slot:footer>
                <button type="button" class="nexora-btn nexora-btn-outline" x-on:click="formOpen = false">Cancel</button>
                <button type="submit" form="job-form" class="nexora-btn nexora-btn-primary disabled:opacity-60" x-bind:disabled="formSaving">
                    <x-lucide-save class="h-4 w-4" />
                    <span x-text="formSaving ? 'Saving…' : (isEditing ? 'Save Changes' : 'Save Job Note')">Save Job Note</span>
                </button>
            </x-slot:footer>
        </x-modal>
    </div>
</x-layouts.app>
