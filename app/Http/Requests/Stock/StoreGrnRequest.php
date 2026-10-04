<?php

namespace App\Http\Requests\Stock;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGrnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('grn.create');
    }

    /**
     * Prepare the data for validation: trim text and serials, and treat a blank selling price as "use product price".
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $items = array_map(function (mixed $item): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                $serials = $item['serials'] ?? [];

                return [
                    ...$item,
                    'selling_price' => filled($item['selling_price'] ?? null) ? $item['selling_price'] : null,
                    'serials' => is_array($serials)
                        ? array_values(array_map(fn (mixed $serial): mixed => is_string($serial) ? trim($serial) : $serial, $serials))
                        : $serials,
                ];
            }, $items);
        }

        $this->merge([
            'supplier_id' => filled($this->input('supplier_id')) ? $this->input('supplier_id') : null,
            'note' => trim((string) $this->input('note')),
            'items' => $items,
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Whether a line needs a quantity or serials
     * depends on its product, so that is checked after these rules pass.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'location' => ['required', Rule::in(array_keys(StockService::LOCATIONS))],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.cost_price' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'items.*.serials' => ['nullable', 'array', 'max:10000'],
            'items.*.serials.*' => ['required', 'string', 'max:100'],
        ];
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
                if ($validator->errors()->isEmpty()) {
                    $this->validateQuantities($validator);
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
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.product_id.required' => 'Select a product.',
            'items.*.product_id.exists' => 'Select a product.',
            'items.*.cost_price.required' => 'Enter the cost price.',
            'items.*.cost_price.gt' => 'The cost price must be greater than 0.',
            'items.*.qty.min' => 'The quantity must be at least 1.',
            'items.*.serials.*.required' => 'Enter a serial number for every unit.',
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
            'supplier_id' => 'supplier',
            'items.*.cost_price' => 'cost price',
            'items.*.selling_price' => 'selling price',
            'items.*.qty' => 'quantity',
        ];
    }

    /**
     * Non-serial lines need a quantity; serial lines need one new, unique serial per unit.
     */
    private function validateQuantities(Validator $validator): void
    {
        $items = $this->input('items');
        $products = Product::query()->whereKey(array_column($items, 'product_id'))->get()->keyBy('id');
        $seen = [];

        foreach ($items as $index => $item) {
            $product = $products[$item['product_id']];

            if (! $product->track_serial) {
                if (blank($item['qty'] ?? null)) {
                    $validator->errors()->add("items.{$index}.qty", 'Enter the quantity received.');
                }

                continue;
            }

            $serials = $item['serials'] ?? [];

            if ($serials === []) {
                $validator->errors()->add("items.{$index}.serials", 'Add a serial number for every unit.');

                continue;
            }

            $existing = array_map('mb_strtolower', $product->existingSerials($serials));

            foreach ($serials as $serialIndex => $serial) {
                $key = $product->id.'|'.mb_strtolower($serial);

                if (in_array(mb_strtolower($serial), $existing, true)) {
                    $validator->errors()->add("items.{$index}.serials.{$serialIndex}", "Serial \"{$serial}\" already exists for this product.");
                } elseif (isset($seen[$key])) {
                    $validator->errors()->add("items.{$index}.serials.{$serialIndex}", "Serial \"{$serial}\" is entered twice.");
                }

                $seen[$key] = true;
            }
        }
    }
}
