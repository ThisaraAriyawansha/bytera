<?php

namespace App\Http\Requests\Stock;

use App\Models\Product;
use App\Services\StockService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Item lines for documents that take stock from one location (transfers, stock outs): a quantity for
 * ordinary products, or the picked serial units for serial-tracked ones.
 */
trait ValidatesStockLines
{
    /**
     * Normalize the submitted lines: unit ids become a list.
     *
     * @return mixed the items, ready to merge
     */
    protected function normalizedItems(): mixed
    {
        $items = $this->input('items');

        if (! is_array($items)) {
            return $items;
        }

        return array_map(function (mixed $item): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $unitIds = $item['unit_ids'] ?? [];

            return [...$item, 'unit_ids' => is_array($unitIds) ? array_values($unitIds) : $unitIds];
        }, $items);
    }

    /**
     * @return array<string, array<mixed>>
     */
    protected function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['array'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_ids' => ['nullable', 'array', 'max:10000'],
            'items.*.unit_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function itemMessages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.product_id.required' => 'Select a product.',
            'items.*.product_id.exists' => 'Select a product.',
            'items.*.product_id.distinct' => 'This product is listed twice.',
            'items.*.qty.min' => 'The quantity must be at least 1.',
        ];
    }

    /**
     * Non-serial lines need a quantity, serial lines need picked units, and neither may ask for more than
     * the location holds. The service re-checks under row locks when it saves.
     */
    protected function validateLines(Validator $validator, string $location): void
    {
        $items = $this->input('items');
        $products = Product::query()->whereKey(array_column($items, 'product_id'))->get()->keyBy('id');
        $label = StockService::LOCATIONS[$location];

        foreach ($items as $index => $item) {
            $product = $products[$item['product_id']];
            $available = $product->{"{$location}_stock"};

            if ($product->track_serial) {
                $qty = count($item['unit_ids'] ?? []);

                if ($qty === 0) {
                    $validator->errors()->add("items.{$index}.unit_ids", 'Pick the serial numbers.');

                    continue;
                }
            } else {
                $qty = (int) ($item['qty'] ?? 0);

                if ($qty === 0) {
                    $validator->errors()->add("items.{$index}.qty", 'Enter the quantity.');

                    continue;
                }
            }

            if ($qty > $available) {
                $validator->errors()->add("items.{$index}.qty", "Only {$available} of \"{$product->name}\" in {$label}.");
            }
        }
    }
}
