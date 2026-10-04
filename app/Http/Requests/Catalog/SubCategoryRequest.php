<?php

namespace App\Http\Requests\Catalog;

use App\Models\SubCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubCategoryRequest extends FormRequest
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
     * Get the validation rules that apply to the request. Names are unique within their main category.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var SubCategory|null $subCategory */
        $subCategory = $this->route('subCategory');

        return [
            'main_category_id' => ['required', 'integer', Rule::exists('main_categories', 'id')],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('sub_categories', 'name')
                    ->where('main_category_id', $this->integer('main_category_id'))
                    ->ignore($subCategory),
            ],
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
            'main_category_id.required' => 'Choose a main category.',
            'main_category_id.exists' => 'Choose a main category.',
            'name.unique' => 'This main category already has a subcategory with this name.',
        ];
    }
}
