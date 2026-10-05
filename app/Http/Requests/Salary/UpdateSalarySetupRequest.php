<?php

namespace App\Http\Requests\Salary;

use App\Models\SalaryPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalarySetupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('salary.manageConfig');
    }

    /**
     * Prepare the data for validation: "Clear" sends an empty type.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['salary_type' => $this->input('salary_type') ?: null]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('salary_type');

        return [
            'salary_type' => ['nullable', Rule::in(array_keys(SalaryPayment::TYPES))],
            'salary_monthly_amount' => [Rule::requiredIf(in_array($type, ['monthly', 'hybrid'], true)), 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'salary_commission_percent' => [Rule::requiredIf(in_array($type, ['commission', 'hybrid'], true)), 'nullable', 'numeric', 'min:0', 'max:100'],
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
            'salary_type.in' => 'Choose Monthly, Commission or Hybrid.',
            'salary_monthly_amount.required' => 'Enter the monthly amount.',
            'salary_commission_percent.required' => 'Enter the commission %.',
            'salary_commission_percent.max' => 'The commission must be between 0 and 100%.',
        ];
    }
}
