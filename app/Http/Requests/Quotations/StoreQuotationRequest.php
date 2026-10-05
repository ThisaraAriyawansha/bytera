<?php

namespace App\Http\Requests\Quotations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('quotations.view');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        $this->merge([
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => trim((string) $this->input('customer_phone')),
            'customer_address' => trim((string) $this->input('customer_address')),
            'note' => trim((string) $this->input('note')),
            'items' => is_array($items) ? array_map(fn (mixed $item): mixed => is_array($item) ? [
                ...$item,
                'product_id' => filled($item['product_id'] ?? null) ? $item['product_id'] : null,
                'product_name' => trim((string) ($item['product_name'] ?? '')),
                'discount' => filled($item['discount'] ?? null) ? $item['discount'] : 0,
            ] : $item, $items) : $items,
        ]);
    }

    /**
     * Get the validation rules that apply to the request. An item is a product from the catalogue or a typed
     * free-text line; the discount is per unit, like the POS.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'items.*.product_name' => ['required', 'string', 'max:150'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'lte:items.*.unit_price'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
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
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.product_name.required' => 'Enter the item name.',
            'items.*.discount.lte' => "The discount can't be more than the unit price.",
            'valid_until.after_or_equal' => "The valid until date can't be in the past.",
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
            'items.*.qty' => 'quantity',
            'items.*.unit_price' => 'unit price',
            'items.*.discount' => 'discount',
            'discount_amount' => 'discount',
            'valid_until' => 'valid until date',
        ];
    }
}
