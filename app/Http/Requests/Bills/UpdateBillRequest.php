<?php

namespace App\Http\Requests\Bills;

use App\Models\Sale;
use App\Rules\StrictEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('bills.edit');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $optional = fn (string $field): ?string => filled($this->input($field)) ? trim((string) $this->input($field)) : null;

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => $optional('customer_phone'),
            'customer_email' => filled($this->input('customer_email')) ? Str::lower(trim((string) $this->input('customer_email'))) : null,
            'note' => $optional('note'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Only the customer's name / phone / email, the note and
     * the payment method can change (SPEC §8.5).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_email' => ['nullable', 'string', new StrictEmail],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', Rule::in(array_keys(Sale::PAYMENT_METHODS))],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'customer name',
            'customer_phone' => 'phone number',
            'customer_email' => 'email',
            'payment_method' => 'payment method',
        ];
    }
}
