<?php

namespace App\Http\Requests\Salary;

use App\Models\SalaryPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueSalaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('salary.issue');
    }

    /**
     * Prepare the data for validation: no drawer shift unless "Paid from cash drawer" was ticked, and no linked
     * items or commission figures on a monthly payment.
     */
    protected function prepareForValidation(): void
    {
        $isMonthly = $this->input('type') === 'monthly';

        $this->merge([
            'period_label' => trim((string) $this->input('period_label')),
            'note' => trim((string) $this->input('note')),
            'shift_id' => $this->boolean('from_drawer') ? $this->input('shift_id') : null,
            'sale_ids' => $isMonthly ? [] : ($this->input('sale_ids') ?? []),
            'job_ids' => $isMonthly ? [] : ($this->input('job_ids') ?? []),
            'commission_base' => $isMonthly ? null : $this->input('commission_base'),
            'commission_percent' => $isMonthly ? null : $this->input('commission_percent'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Unclaimed items and the open shift are checked by
     * SalaryService against locked rows.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasCommission = in_array($this->input('type'), ['commission', 'hybrid'], true);

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'type' => ['required', Rule::in(array_keys(SalaryPayment::TYPES))],
            'commission_base' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'commission_percent' => [Rule::requiredIf($hasCommission), 'nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'period_label' => ['required', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'from_drawer' => ['boolean'],
            'shift_id' => [Rule::requiredIf($this->boolean('from_drawer')), 'nullable', 'integer', Rule::exists('shifts', 'id')],
            'sale_ids' => ['array', 'max:500'],
            'sale_ids.*' => ['integer', 'distinct'],
            'job_ids' => ['array', 'max:500'],
            'job_ids.*' => ['integer', 'distinct'],
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
            'user_id.required' => 'Choose the employee to pay.',
            'user_id.exists' => 'That employee no longer exists.',
            'type.in' => 'Choose Monthly, Commission or Hybrid.',
            'commission_percent.required' => 'Enter the commission %.',
            'commission_percent.max' => 'The commission must be between 0 and 100%.',
            'amount.required' => 'Enter the amount to pay.',
            'amount.gt' => 'The amount to pay must be greater than 0.',
            'period_label.required' => 'Enter the period, e.g. August 2026.',
            'shift_id.required' => 'Choose the open shift the cash was taken from.',
            'shift_id.exists' => 'That shift no longer exists.',
        ];
    }
}
