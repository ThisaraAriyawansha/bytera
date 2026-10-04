<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    use ValidatesSerialNumbers;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('products.edit');
    }

    /**
     * Prepare the data for validation: trim text and default the optional numbers.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'sku' => trim((string) $this->input('sku')),
            'barcode' => filled($this->input('barcode')) ? trim((string) $this->input('barcode')) : null,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'low_stock_alert' => filled($this->input('low_stock_alert')) ? $this->input('low_stock_alert') : 5,
            'warranty_months' => filled($this->input('warranty_months')) ? $this->input('warranty_months') : 0,
            'initial_stock' => filled($this->input('initial_stock')) ? $this->input('initial_stock') : 0,
            'cost_price' => filled($this->input('cost_price')) ? $this->input('cost_price') : null,
            'serials' => $this->trimmedSerials(),
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Initial stock and serials only apply when adding.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->product();

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')],
            'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product)],
            'main_category_id' => ['required', 'integer', Rule::exists('main_categories', 'id')],
            'sub_category_id' => [
                'required',
                'integer',
                Rule::exists('sub_categories', 'id')->where('main_category_id', $this->integer('main_category_id')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'low_stock_alert' => ['required', 'integer', 'min:0', 'max:100000'],
            'warranty_months' => ['required', 'integer', 'min:0', 'max:240'],
            'track_serial' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ];

        if ($product !== null) {
            return $rules;
        }

        return [
            ...$rules,
            'initial_stock' => ['required', 'integer', 'min:0', 'max:10000'],
            'cost_price' => ['nullable', 'required_unless:initial_stock,0', 'numeric', 'min:0', 'max:9999999999'],
            'serials' => ['array', $this->boolean('track_serial') ? 'size:'.$this->integer('initial_stock') : 'max:0'],
            'serials.*' => $this->serialNumberRules(),
        ];
    }

    /**
     * Get the "after" validation callables: a product with stock batches can't switch serial tracking.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->product();

                if ($product !== null && $this->boolean('track_serial') !== $product->track_serial && $product->batches()->exists()) {
                    $validator->errors()->add('track_serial', 'Serial tracking can\'t be changed once the product has stock batches.');
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
            'brand_id.required' => 'Choose a brand.',
            'brand_id.exists' => 'Choose a brand.',
            'main_category_id.required' => 'Choose a main category.',
            'main_category_id.exists' => 'Choose a main category.',
            'sub_category_id.required' => 'Choose a subcategory.',
            'sub_category_id.exists' => 'Choose a subcategory of the selected main category.',
            'sku.unique' => 'Another product already uses this SKU.',
            'barcode.unique' => 'Another product already uses this barcode.',
            'cost_price.required_unless' => 'Enter the cost price for the initial stock.',
            'serials.size' => 'Enter one serial number per unit of initial stock.',
            'serials.max' => 'Serial numbers are only for products that track them.',
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
            'sku' => 'SKU',
            'selling_price' => 'selling price',
            'low_stock_alert' => 'low stock alert',
            'warranty_months' => 'warranty',
            'initial_stock' => 'initial stock',
            'cost_price' => 'cost price',
        ];
    }

    /**
     * Get the product attributes to save (stock counters are managed by the StockService).
     *
     * @return array{name: string, brand_id: int, sku: string, barcode: ?string, main_category_id: int, sub_category_id: int, description: ?string, selling_price: string|int|float, low_stock_alert: int, warranty_months: int, track_serial: bool, active: bool}
     */
    public function productAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'brand_id' => (int) $validated['brand_id'],
            'sku' => $validated['sku'],
            'barcode' => $validated['barcode'],
            'main_category_id' => (int) $validated['main_category_id'],
            'sub_category_id' => (int) $validated['sub_category_id'],
            'description' => $validated['description'],
            'selling_price' => $validated['selling_price'],
            'low_stock_alert' => (int) $validated['low_stock_alert'],
            'warranty_months' => (int) $validated['warranty_months'],
            'track_serial' => (bool) $validated['track_serial'],
            'active' => (bool) $validated['active'],
        ];
    }

    /**
     * Get the product being edited, or null when adding one.
     */
    private function product(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
