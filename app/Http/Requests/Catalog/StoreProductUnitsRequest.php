<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProductUnitsRequest extends FormRequest
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
        $this->merge(['serials' => $this->trimmedSerials()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serials' => ['required', 'array', 'min:1', 'max:10000'],
            'serials.*' => $this->serialNumberRules(),
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
                    $this->rejectExistingSerials($validator, $this->route('product'), $this->input('serials'));
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
}
