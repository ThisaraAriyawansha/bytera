<?php

namespace App\Http\Requests\Suppliers;

use App\Models\SupplierPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: recording a payment and editing one
     * are separate permissions.
     */
    public function authorize(): bool
    {
        return $this->route('payment') === null
            ? $this->user()->can('suppliers.recordPayment')
            : $this->user()->can('suppliers.editPayment');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => trim((string) $this->input('reference')),
            'note' => trim((string) $this->input('note')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request. The "not more than the balance" check runs
     * in SupplierService, against the locked supplier row.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'method' => ['required', Rule::in(array_keys(SupplierPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
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
            'amount.gt' => 'The amount must be greater than 0.',
            'method.in' => 'Choose Cash, Bank Transfer, Cheque or Other.',
        ];
    }
}
