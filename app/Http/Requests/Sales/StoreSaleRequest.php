<?php

namespace App\Http\Requests\Sales;

use App\Models\Sale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('sales.view');
    }

    /**
     * Get the validation rules that apply to the request. The shape only — stock, prices and totals are
     * checked by SaleService under row locks.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'items' => ['nullable', 'array', 'max:200'],
            'items.*' => ['array'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'items.*.batch_id' => ['nullable', 'integer'],
            'items.*.unit_ids' => ['nullable', 'array', 'max:10000'],
            'items.*.unit_ids.*' => ['required', 'integer', 'distinct'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'services' => ['nullable', 'array', 'max:100'],
            'services.*' => ['array'],
            'services.*.service_id' => ['required', 'integer'],
            'services.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'services.*.fields' => ['nullable', 'array'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'points_redeemed' => ['nullable', 'integer', 'min:0'],
            'payments' => ['required', 'array', 'min:1', 'max:3'],
            'payments.*' => ['array'],
            'payments.*.method' => ['required', 'distinct', Rule::in(array_keys(Sale::PAYMENT_METHODS))],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'card_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'kokopay_charge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'expected_total' => ['required', 'numeric'],
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
            'payments.required' => 'Choose a payment method.',
            'payments.*.method.in' => 'Choose a payment method.',
            'card_charge_percent.max' => 'The surcharge must be between 0 and 100%.',
            'kokopay_charge_percent.max' => 'The surcharge must be between 0 and 100%.',
        ];
    }
}
