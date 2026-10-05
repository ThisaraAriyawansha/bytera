<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('finance.addExpense');
    }

    /**
     * Prepare the data for validation: no drawer shift unless "Paid from cash drawer" was ticked.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'note' => trim((string) $this->input('note')),
            'shift_id' => $this->boolean('from_drawer') ? $this->input('shift_id') : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Whether the shift is still open is checked by
     * ExpenseService against the locked shift row.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(Expense::manualCategories()))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
            'from_drawer' => ['boolean'],
            'shift_id' => [Rule::requiredIf($this->boolean('from_drawer')), 'nullable', 'integer', Rule::exists('shifts', 'id')],
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
            'category.in' => 'Choose Rent, Utilities, Maintenance, Marketing or Other. Salaries are recorded from the Salary page.',
            'amount.gt' => 'The amount must be greater than 0.',
            'shift_id.required' => 'Choose the open shift the cash was taken from.',
            'shift_id.exists' => 'That shift no longer exists.',
        ];
    }
}
