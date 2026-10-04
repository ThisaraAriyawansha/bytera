<?php

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateStockOutRequest extends StockOutDetailsRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('stockOut.edit');
    }

    /**
     * Get the validation rules that apply to the request. Location and items are locked.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->detailRules();
    }
}
