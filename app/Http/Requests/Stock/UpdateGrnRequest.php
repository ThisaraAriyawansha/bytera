<?php

namespace App\Http\Requests\Stock;

use App\Models\Grn;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('grn.edit');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'supplier_id' => filled($this->input('supplier_id')) ? $this->input('supplier_id') : null,
            'note' => trim((string) $this->input('note')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request. Only the supplier, note and each line's
     * prices can change; quantities and serials are locked.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Grn $grn */
        $grn = $this->route('grn');

        return [
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer', 'distinct', Rule::exists('grn_items', 'id')->where('grn_id', $grn->id)],
            'items.*.cost_price' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
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
            'items.*.cost_price.required' => 'Enter the cost price.',
            'items.*.cost_price.gt' => 'The cost price must be greater than 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['supplier_id' => 'supplier', 'items.*.selling_price' => 'selling price'];
    }
}
