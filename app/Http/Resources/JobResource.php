<?php

namespace App\Http\Resources;

use App\Models\Job;
use App\Models\JobStatusHistory;
use App\Services\JobService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Job
 */
class JobResource extends JsonResource
{
    /**
     * Transform the resource into an array for the job view modal and the Edit Job form.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $finalCents = JobService::finalCostCents($this->resource);

        return [
            'id' => $this->id,
            'job_no' => $this->job_no,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_company' => $this->customer_company,
            'customer_address' => $this->customer_address,
            'customer_city' => $this->customer_city,
            'customer_phone' => $this->customer_phone,
            'customer_phone2' => $this->customer_phone2,
            'customer_email' => $this->customer_email,
            'device_type' => $this->device_type,
            'device_type_other' => $this->device_type_other,
            'device_label' => $this->deviceLabel(),
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_no' => $this->serial_no,
            'color' => $this->color,
            'parts' => $this->parts ?? [],
            'fault_description' => $this->fault_description,
            'accessories' => $this->accessories ?? [],
            'accessories_other' => $this->accessories_other,
            'physical_condition' => $this->physical_condition ?? [],
            'special_notes' => $this->special_notes,
            'received_by_name' => $this->received_by_name,
            'assigned_technician_id' => $this->assigned_technician_id,
            'assigned_technician_name' => $this->assigned_technician_name,
            'services' => $this->services ?? [],
            'services_total' => JobService::servicesTotalCents($this->services ?? []) / 100,
            'estimated_cost' => (float) $this->estimated_cost,
            'advance_paid' => (float) $this->advance_paid,
            'repair_cost' => $this->repair_cost === null ? null : (float) $this->repair_cost,
            'final_cost' => $finalCents / 100,
            'balance' => max(0, $finalCents - (int) round((float) $this->advance_paid * 100)) / 100,
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'expected_delivery_label' => $this->expected_delivery_date?->format('M j, Y'),
            'date_returned' => $this->date_returned?->format('M j, Y g:i A'),
            'received_at' => $this->created_at->format('M j, Y g:i A'),
            'history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn (JobStatusHistory $entry): array => [
                'id' => $entry->id,
                'status' => $entry->status,
                'status_label' => Job::STATUSES[$entry->status]['label'] ?? $entry->status,
                'note' => $entry->note,
                'repair_cost' => $entry->repair_cost === null ? null : (float) $entry->repair_cost,
                'updated_by_name' => $entry->updated_by_name,
                'date' => $entry->created_at->format('M j, Y g:i A'),
            ])->values()),
            'urls' => [
                'show' => route('jobs.show', $this->id),
                'update' => route('jobs.update', $this->id),
                'status' => route('jobs.status', $this->id),
                'email' => route('jobs.email', $this->id),
            ],
        ];
    }
}
