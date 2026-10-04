<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductBatchRequest extends FormRequest
{
    use ValidatesSerialNumbers;

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
        $this->merge([
            'selling_price' => filled($this->input('selling_price')) ? $this->input('selling_price') : null,
            'note' => trim((string) $this->input('note')),
            'serials' => $this->trimmedSerials(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Serial products send one serial per unit instead of a quantity.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ];

        if ($this->product()->track_serial) {
            return [
                ...$rules,
                'serials' => ['required', 'array', 'min:1', 'max:10000'],
                'serials.*' => $this->serialNumberRules(),
            ];
        }

        return [...$rules, 'qty' => ['required', 'integer', 'min:1', 'max:10000']];
    }

    /**
     * Get the "after" validation callables.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->product()->track_serial && $validator->errors()->isEmpty()) {
                    $this->rejectExistingSerials($validator, $this->product(), $this->input('serials'));
                }
            },
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
            ...$this->serialNumberMessages(),
            'serials.required' => 'Add at least one serial number.',
            'serials.min' => 'Add at least one serial number.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cost_price' => 'cost price', 'selling_price' => 'selling price', 'qty' => 'quantity'];
    }

    /**
     * Get the quantity received: the number of serials for serial products.
     */
    public function quantity(): int
    {
        return $this->product()->track_serial ? count($this->validated('serials')) : (int) $this->validated('qty');
    }

    /**
     * Get the product the batch is added to.
     */
    private function product(): Product
    {
        return $this->route('product');
    }
}
