<?php

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Validation\Validator;

/**
 * Shared handling of the "one serial number per unit" inputs on the product forms.
 */
trait ValidatesSerialNumbers
{
    /**
     * Get the submitted serial numbers trimmed, leaving non-array input for the validator to reject.
     */
    protected function trimmedSerials(): mixed
    {
        $serials = $this->input('serials', []);

        if (! is_array($serials)) {
            return $serials;
        }

        return array_values(array_map(fn (mixed $serial): mixed => is_string($serial) ? trim($serial) : $serial, $serials));
    }

    /**
     * Get the rules for each serial number.
     *
     * @return list<string>
     */
    protected function serialNumberRules(): array
    {
        return ['required', 'string', 'max:100', 'distinct:ignore_case'];
    }

    /**
     * Get the messages for the serial number rules.
     *
     * @return array<string, string>
     */
    protected function serialNumberMessages(): array
    {
        return [
            'serials.*.required' => 'Enter a serial number for every unit.',
            'serials.*.distinct' => 'Each unit needs a different serial number.',
        ];
    }

    /**
     * Add an error for every serial number the product already has a unit for.
     *
     * @param  list<string>  $serials
     */
    protected function rejectExistingSerials(Validator $validator, Product $product, array $serials): void
    {
        $existing = array_map('mb_strtolower', $product->existingSerials($serials));

        foreach ($serials as $index => $serial) {
            if (in_array(mb_strtolower($serial), $existing, true)) {
                $validator->errors()->add("serials.{$index}", "Serial \"{$serial}\" already exists for this product.");
            }
        }
    }
}
