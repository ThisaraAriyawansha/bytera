<?php

namespace App\Http\Requests\Suppliers;

use App\Rules\StrictEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: anyone on the page may add a supplier,
     * editing an existing supplier's contact needs `suppliers.editContact`.
     */
    public function authorize(): bool
    {
        return $this->route('supplier') === null
            ? $this->user()->can('suppliers.view')
            : $this->user()->can('suppliers.editContact');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => trim((string) $this->input('phone')),
            'email' => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
            'address' => filled($this->input('address')) ? trim((string) $this->input('address')) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Balances are never taken from the form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', new StrictEmail],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['phone' => 'phone number'];
    }
}
