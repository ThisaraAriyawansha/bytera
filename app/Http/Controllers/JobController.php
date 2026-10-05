<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\StoreJobRequest;
use App\Http\Requests\Jobs\UpdateJobRequest;
use App\Http\Requests\Jobs\UpdateJobStatusRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\JobResource;
use App\Mail\JobReceivedMail;
use App\Mail\JobUpdateMail;
use App\Models\Job;
use App\Models\JobStatusHistory;
use App\Models\ShopSetting;
use App\Models\User;
use App\Rules\StrictEmail;
use App\Services\JobService;
use App\Services\Numbering;
use App\Support\Money;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class JobController extends Controller
{
    /**
     * The most jobs a picker search returns.
     */
    private const SEARCH_LIMIT = 20;

    public function __construct(private JobService $jobs) {}

    /**
     * The Jobs page (SPEC §8.7): status chips with counts, status / date / search filters and the paginated table,
     * plus everything the New Job Note form needs.
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('jobs.index', [
            'jobs' => $this->filteredJobs($filters)->paginate(Pagination::PER_PAGE)->withQueryString(),
            'statusCounts' => Job::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'filters' => $filters,
            'canEdit' => $request->user()->can('jobs.edit'),
            'jobConfig' => [
                'nextJobNo' => Numbering::previewJobNo(),
                'statuses' => Job::STATUSES,
                'deviceTypes' => Job::DEVICE_TYPES,
                'partPresets' => Job::PART_PRESETS,
                'accessories' => Job::ACCESSORIES,
                'conditions' => Job::CONDITIONS,
                'technicians' => JobService::technicians()
                    ->map(fn (User $technician): array => ['id' => $technician->id, 'name' => $technician->name ?: $technician->email])
                    ->all(),
                'urls' => [
                    'store' => route('jobs.store'),
                    'customerSearch' => route('api.customers.search'),
                ],
            ],
        ]);
    }

    /**
     * Export Report: the filtered jobs as CSV, each with its full status history.
     */
    public function export(Request $request): StreamedResponse
    {
        $jobs = $this->filteredJobs($this->filters($request))->with(['statusHistory' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]);

        return response()->streamDownload(function () use ($jobs): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Job No', 'Received', 'Customer', 'Company', 'Mobile', 'Mobile 2', 'Email', 'Device', 'Serial No', 'Fault',
                'Technician', 'Received By', 'Services', 'Estimated Cost', 'Advance Paid', 'Repair Cost', 'Expected Delivery',
                'Status', 'Date Returned', 'Status History',
            ], escape: '');

            foreach ($jobs->lazy(200) as $job) {
                fputcsv($output, [
                    $job->job_no,
                    $job->created_at->format('Y-m-d H:i'),
                    $job->customer_name,
                    $job->customer_company,
                    $job->customer_phone,
                    $job->customer_phone2,
                    $job->customer_email,
                    $job->deviceLabel(),
                    $job->serial_no,
                    $job->fault_description,
                    $job->assigned_technician_name,
                    $job->received_by_name,
                    JobService::auditValues(['services' => $job->services])['services'],
                    $job->estimated_cost,
                    $job->advance_paid,
                    $job->repair_cost,
                    $job->expected_delivery_date?->toDateString(),
                    $job->statusLabel(),
                    $job->date_returned?->format('Y-m-d H:i'),
                    $job->statusHistory
                        ->map(fn (JobStatusHistory $entry): string => implode(' · ', array_filter([
                            $entry->created_at->format('Y-m-d H:i'),
                            Job::STATUSES[$entry->status]['label'] ?? $entry->status,
                            $entry->note,
                            $entry->repair_cost === null ? null : 'Repair cost '.Money::format($entry->repair_cost),
                            'by '.$entry->updated_by_name,
                        ])))
                        ->implode(' | '),
                ], escape: '');
            }

            fclose($output);
        }, 'jobs-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Save a New Job Note: the job (pending) and its first history row "Job received".
     */
    public function store(StoreJobRequest $request): JsonResponse
    {
        $job = $this->jobs->create($request->validated(), $request->user());

        return response()->json([
            'message' => "{$job->job_no} saved.",
            'showUrl' => route('jobs.show', $job),
            'nextJobNo' => Numbering::previewJobNo(),
        ], 201);
    }

    /**
     * A job with its history (newest first) and the A4 job note for the view modal.
     */
    public function show(Job $job): JsonResponse
    {
        return response()->json($this->viewPayload($job));
    }

    /**
     * Edit Job (`jobs.edit`): details only, audit logged.
     */
    public function update(UpdateJobRequest $request, Job $job): JsonResponse
    {
        $changes = $this->jobs->update($job, $request->validated(), $request->user());

        return response()->json([
            'message' => $changes === [] ? 'Nothing changed.' : "{$job->job_no} updated.",
            ...$this->viewPayload($job),
        ]);
    }

    /**
     * Update Job Status: new status (and repair cost for Job Done) with a history row.
     */
    public function updateStatus(UpdateJobStatusRequest $request, Job $job): JsonResponse
    {
        $this->jobs->updateStatus($job, $request->validated(), $request->user());

        return response()->json([
            'message' => "{$job->job_no} updated.",
            ...$this->viewPayload($job),
        ]);
    }

    /**
     * Email the customer the job confirmation ("received") or the latest status update ("update"). The job,
     * its email address and amounts are loaded here, never taken from the browser.
     */
    public function email(Request $request, Job $job): JsonResponse
    {
        abort_unless($request->user()->can('jobs.view'), 403);

        $type = $request->validate(['type' => ['required', Rule::in(['received', 'update'])]])['type'];
        $email = (string) $job->customer_email;

        if (! StrictEmail::isValid($email)) {
            throw ValidationException::withMessages([
                'email' => $email === '' ? 'This job has no customer email address.' : "The customer's email address \"{$email}\" is not valid.",
            ]);
        }

        $shop = ShopSetting::current();
        $mail = $type === 'received'
            ? new JobReceivedMail($job, $shop)
            : new JobUpdateMail($job, $job->statusHistory()->latest()->latest('id')->firstOrFail(), $shop);

        try {
            Mail::to($email)->send($mail);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['email' => 'The email could not be sent. Please try again.']);
        }

        return response()->json(['message' => "Email sent to {$email}."]);
    }

    /**
     * Job picker for the SearchableSelect (e.g. Stock Out → Job / Repair): latest jobs matching the job number
     * ("123" finds JOB-00123), customer name or phone. With no term, the latest jobs.
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless(Gate::any(['jobs.view', 'stockOut.create', 'stockOut.edit']), 403);

        $term = trim((string) $request->query('q'));
        $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        $jobs = Job::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('job_no', 'like', "%{$pattern}%")
                ->when(ctype_digit($term), fn (Builder $query) => $query->orWhere('job_no', sprintf('JOB-%05d', (int) $term)))
                ->orWhere('customer_name', 'like', "{$pattern}%")
                ->orWhere('customer_phone', 'like', "{$pattern}%")
                ->orWhere('customer_phone2', 'like', "{$pattern}%")))
            ->latest()
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'job_no', 'customer_name', 'device_type', 'device_type_other', 'brand', 'model']);

        return response()->json($jobs->map(fn (Job $job): array => [
            'value' => $job->id,
            'label' => $job->job_no,
            'description' => collect([
                $job->customer_name,
                trim(($job->device_type === 'Other' ? $job->device_type_other : $job->device_type).' '.$job->brand.' '.$job->model),
            ])->filter()->implode(' · '),
        ])->values());
    }

    /**
     * POS "Find Job to Bill": finished (Job Done) jobs matching the search, each with its billable lines.
     */
    public function billable(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        $jobs = Job::query()
            ->with('customer')
            ->where('status', 'done')
            ->when($term !== '', fn (Builder $query) => $query->matching($term))
            ->latest()
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        return response()->json(['data' => $jobs->map(fn (Job $job): array => [
            'id' => $job->id,
            'job_no' => $job->job_no,
            'customer_name' => $job->customer_name,
            'customer_phone' => $job->customer_phone,
            'device_label' => $job->deviceLabel(),
            'lines' => JobService::billableLines($job),
            'customer' => $job->customer === null ? null : CustomerResource::make($job->customer)->resolve(),
        ])->values()]);
    }

    /**
     * The view modal payload: the job with its history and the rendered A4 job note.
     *
     * @return array{job: array<string, mixed>, printHtml: string}
     */
    private function viewPayload(Job $job): array
    {
        $job = $job->fresh()->load(['statusHistory' => fn ($query) => $query->latest()->latest('id')]);

        return [
            'job' => JobResource::make($job)->resolve(),
            'printHtml' => view('jobs.print', ['job' => $job, 'shop' => ShopSetting::current()])->render(),
        ];
    }

    /**
     * The status / date / search filters from the query string. Dates are optional (all jobs by default).
     *
     * @return array{status: ?string, from: ?string, to: ?string, search: string}
     */
    private function filters(Request $request): array
    {
        $status = $request->query('status');

        return [
            'status' => is_string($status) && array_key_exists($status, Job::STATUSES) ? $status : null,
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'search' => trim((string) $request->query('search')),
        ];
    }

    /**
     * @param  array{status: ?string, from: ?string, to: ?string, search: string}  $filters
     * @return Builder<Job>
     */
    private function filteredJobs(array $filters): Builder
    {
        return Job::query()
            ->when($filters['status'] !== null, fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'])->startOfDay()))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->where('created_at', '<=', CarbonImmutable::parse($filters['to'])->endOfDay()))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->matching($filters['search']))
            ->latest()
            ->latest('id');
    }

    /**
     * A Y-m-d date from the query string, or null.
     */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
