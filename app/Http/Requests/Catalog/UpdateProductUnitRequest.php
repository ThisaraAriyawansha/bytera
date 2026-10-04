<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('products.batch.edit');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['serial_number' => trim((string) $this->input('serial_number'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serial_number' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Get the "after" validation callables: the serial must stay unique within the product.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Product $product */
                $product = $this->route('product');
                /** @var ProductUnit $unit */
                $unit = $this->route('unit');
                $serial = (string) $this->input('serial_number');

                if ($serial !== '' && $product->existingSerials([$serial], $unit->id) !== []) {
                    $validator->errors()->add('serial_number', "Serial \"{$serial}\" already exists for this product.");
                }
            },
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['serial_number' => 'serial number'];
    }
}
