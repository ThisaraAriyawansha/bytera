<?php

namespace App\Http\Requests\Jobs;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateJobRequest extends JobDetailsRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('jobs.edit');
    }

    /**
     * Get the validation rules that apply to the request. Job no, status, repair cost and dates are locked.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->detailRules();
    }
}
