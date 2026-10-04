<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class JobController extends Controller
{
    /**
     * The most jobs a picker search returns.
     */
    private const SEARCH_LIMIT = 20;

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
}
