<?php

namespace App\Http\Requests\Stock;

use App\Models\StockOut;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The recipient / reason / job / note fields shared by issuing a stock out and its admin edit.
 */
abstract class StockOutDetailsRequest extends FormRequest
{
    /**
     * Prepare the data for validation: trim text, and drop the job unless the reason is Job / Repair.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient' => trim((string) $this->input('recipient')),
            'reason_detail' => trim((string) $this->input('reason_detail')),
            'note' => trim((string) $this->input('note')),
            'job_id' => $this->input('reason') === 'job' && filled($this->input('job_id')) ? $this->input('job_id') : null,
        ]);
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function detailRules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:255'],
            'reason' => ['required', Rule::in(array_keys(StockOut::REASONS))],
            'reason_detail' => ['nullable', 'required_if:reason,other', 'string', 'max:255'],
            'job_id' => ['nullable', 'required_if:reason,job', 'integer', Rule::exists('jobs', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
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
            'recipient.required' => 'Enter who the stock is issued to.',
            'reason_detail.required_if' => 'Describe the reason.',
            'job_id.required_if' => 'Select a job.',
            'job_id.exists' => 'Select a job.',
        ];
    }
}
