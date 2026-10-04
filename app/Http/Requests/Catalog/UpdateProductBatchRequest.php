<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductBatchRequest extends FormRequest
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
        $this->merge([
            'selling_price' => filled($this->input('selling_price')) ? $this->input('selling_price') : null,
            'note' => trim((string) $this->input('note')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'total_qty' => ['required', 'integer', 'min:0', 'max:100000'],
            'remaining_qty' => ['required', 'integer', 'min:0', 'lte:total_qty'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the "after" validation callables: serial batches follow their units, so their quantities are locked.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Product $product */
                $product = $this->route('product');
                /** @var ProductBatch $batch */
                $batch = $this->route('batch');

                $quantitiesChanged = $this->integer('total_qty') !== $batch->total_qty
                    || $this->integer('remaining_qty') !== $batch->remaining_qty;

                if ($product->track_serial && $quantitiesChanged) {
                    $validator->errors()->add('remaining_qty', 'Quantities of a serial-tracked batch follow its serial numbers — add or remove serials instead.');
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
            'remaining_qty.lte' => 'Remaining can\'t be more than the total quantity.',
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
            'cost_price' => 'cost price',
            'selling_price' => 'selling price',
            'total_qty' => 'total quantity',
            'remaining_qty' => 'remaining quantity',
        ];
    }
}
