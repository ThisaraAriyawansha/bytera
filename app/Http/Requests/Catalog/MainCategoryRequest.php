<?php

namespace App\Http\Requests\Catalog;

use App\Models\MainCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MainCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Viewing categories includes adding and editing them.
     */
    public function authorize(): bool
    {
        return $this->user()->can('categories.view');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var MainCategory|null $mainCategory */
        $mainCategory = $this->route('mainCategory');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('main_categories', 'name')->ignore($mainCategory)],
            'description' => ['nullable', 'string', 'max:500'],
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
            'name.unique' => 'A main category with this name already exists.',
        ];
    }
}
