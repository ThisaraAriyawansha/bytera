<?php

namespace App\Http\Requests\Stock;

use App\Services\StockService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockOutRequest extends StockOutDetailsRequest
{
    use ValidatesStockLines;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('stockOut.create');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge(['items' => $this->normalizedItems()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', Rule::in(array_keys(StockService::LOCATIONS))],
            ...$this->detailRules(),
            ...$this->itemRules(),
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
                    $this->validateLines($validator, $this->input('location'));
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
        return [...parent::messages(), ...$this->itemMessages()];
    }
}
