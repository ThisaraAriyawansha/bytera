<?php

namespace App\Http\Requests\Jobs;

use App\Models\Job;
use App\Rules\StrictEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The customer, device, parts, fault, accessories, technician, services and money fields shared by the
 * New Job Note and Edit Job forms (SPEC §8.7).
 */
abstract class JobDetailsRequest extends FormRequest
{
    /**
     * Prepare the data for validation: lower-case the email and drop service rows left completely blank.
     */
    protected function prepareForValidation(): void
    {
        $services = collect($this->input('services', []))
            ->filter(fn (mixed $service): bool => is_array($service))
            ->reject(fn (array $service): bool => blank($service['name'] ?? null) && blank($service['price'] ?? null) && blank($service['freeReason'] ?? null))
            ->values()
            ->all();

        $this->merge([
            'customer_email' => filled($this->input('customer_email')) ? Str::lower(trim((string) $this->input('customer_email'))) : null,
            'services' => is_array($this->input('services')) ? $services : $this->input('services'),
        ]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function detailRules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_company' => ['nullable', 'string', 'max:150'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_city' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_phone2' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'string', new StrictEmail],
            'device_type' => ['required', Rule::in(Job::DEVICE_TYPES)],
            'device_type_other' => ['nullable', 'required_if:device_type,Other', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'parts' => ['nullable', 'array', 'max:50'],
            'parts.*' => ['array'],
            'parts.*.id' => ['nullable', 'string', 'max:40'],
            'parts.*.name' => ['nullable', 'string', 'max:100'],
            'parts.*.spec' => ['nullable', 'string', 'max:200'],
            'parts.*.serialNo' => ['nullable', 'string', 'max:100'],
            'fault_description' => ['required', 'string', 'max:2000'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', Rule::in(Job::ACCESSORIES)],
            'accessories_other' => ['nullable', 'string', 'max:300'],
            'physical_condition' => ['nullable', 'array'],
            'physical_condition.*' => ['string', Rule::in(Job::CONDITIONS)],
            'special_notes' => ['nullable', 'string', 'max:2000'],
            'assigned_technician_id' => ['nullable', 'integer'],
            'services' => ['nullable', 'array', 'max:50'],
            'services.*' => ['array'],
            'services.*.id' => ['nullable', 'string', 'max:40'],
            'services.*.name' => ['required', 'string', 'max:150'],
            'services.*.chargeType' => ['required', Rule::in(['paid', 'free'])],
            'services.*.price' => ['nullable', 'required_if:services.*.chargeType,paid', 'numeric', 'min:0', 'max:9999999999'],
            'services.*.freeReason' => ['nullable', 'string', 'max:200'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'advance_paid' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'expected_delivery_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => "Enter the customer's name.",
            'customer_phone.required' => "Enter the customer's mobile number.",
            'device_type_other.required_if' => 'Specify the device type.',
            'fault_description.required' => 'Describe the fault.',
            'services.*.name.required' => 'Enter the service name.',
            'services.*.price.required_if' => 'Enter the price, or mark the service Free.',
        ];
    }
}
