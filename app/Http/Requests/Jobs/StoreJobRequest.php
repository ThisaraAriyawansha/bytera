<?php

namespace App\Http\Requests\Jobs;

use Illuminate\Contracts\Validation\ValidationRule;

class StoreJobRequest extends JobDetailsRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('jobs.view');
    }

    /**
     * Get the validation rules that apply to the request. A custom job number is optional.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_no' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/'],
            ...$this->detailRules(),
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
            ...parent::messages(),
            'job_no.regex' => 'The job number may only contain letters, numbers, "-" and "/".',
        ];
    }
}
