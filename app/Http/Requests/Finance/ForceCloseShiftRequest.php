<?php

namespace App\Http\Requests\Finance;

use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForceCloseShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: force closing is Admin / Super Admin only (SPEC §4.4).
     */
    public function authorize(): bool
    {
        return $this->user()->can('finance.view') && Permissions::isAdmin($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:500'],
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
            'counted_cash.required' => 'Enter the cash counted in the drawer.',
            'counted_cash.min' => "The counted cash can't be negative.",
        ];
    }
}
