<?php

namespace App\Http\Requests\Catalog;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('services.edit');
    }

    /**
     * Prepare the data for validation: trim text and drop blank dropdown options.
     */
    protected function prepareForValidation(): void
    {
        $fields = $this->input('custom_fields');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'default_price' => filled($this->input('default_price')) ? $this->input('default_price') : 0,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'custom_fields' => is_array($fields) ? array_map($this->trimField(...), $fields) : ($fields ?? []),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'default_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['required', 'boolean'],
            'custom_fields' => ['present', 'array', 'max:30'],
            'custom_fields.*' => ['array:id,label,type,required,placeholder,options'],
            'custom_fields.*.id' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'custom_fields.*.label' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'custom_fields.*.type' => ['required', 'string', Rule::in(array_keys(Service::FIELD_TYPES))],
            'custom_fields.*.required' => ['boolean'],
            'custom_fields.*.placeholder' => ['nullable', 'string', 'max:150'],
            'custom_fields.*.options' => ['array', 'max:50', 'required_if:custom_fields.*.type,select'],
            'custom_fields.*.options.*' => ['string', 'max:100', 'distinct:ignore_case'],
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
            'custom_fields.*.label.required' => 'Every custom field needs a label.',
            'custom_fields.*.label.distinct' => 'Each custom field needs a different label.',
            'custom_fields.*.options.required_if' => 'Add at least one option for this dropdown.',
            'custom_fields.*.options.*.distinct' => 'Dropdown options must be different from each other.',
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
            'default_price' => 'default price',
            'custom_fields.*.label' => 'label',
            'custom_fields.*.type' => 'type',
            'custom_fields.*.placeholder' => 'placeholder',
            'custom_fields.*.options' => 'options',
        ];
    }

    /**
     * Get the service attributes to save, with every custom field in the stored shape.
     *
     * @return array{name: string, default_price: string|int|float, description: ?string, active: bool, custom_fields: list<array{id: string, label: string, type: string, required: bool, placeholder: string, options: list<string>}>}
     */
    public function serviceAttributes(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'default_price' => $validated['default_price'],
            'description' => $validated['description'],
            'active' => (bool) $validated['active'],
            'custom_fields' => array_values(array_map(fn (array $field): array => [
                'id' => filled($field['id'] ?? null) ? $field['id'] : Str::lower(Str::random(10)),
                'label' => $field['label'],
                'type' => $field['type'],
                'required' => (bool) ($field['required'] ?? false),
                'placeholder' => (string) ($field['placeholder'] ?? ''),
                'options' => $field['type'] === 'select' ? array_values($field['options'] ?? []) : [],
            ], $validated['custom_fields'])),
        ];
    }

    /**
     * Trim a custom field's text and drop blank dropdown options.
     */
    private function trimField(mixed $field): mixed
    {
        if (! is_array($field)) {
            return $field;
        }

        if (is_string($field['label'] ?? null)) {
            $field['label'] = trim($field['label']);
        }

        if (is_string($field['placeholder'] ?? null)) {
            $field['placeholder'] = trim($field['placeholder']);
        }

        if (is_array($field['options'] ?? null)) {
            $field['options'] = array_values(array_filter(
                array_map(fn (mixed $option): mixed => is_string($option) ? trim($option) : $option, $field['options']),
                fn (mixed $option): bool => $option !== '' && $option !== null,
            ));
        }

        return $field;
    }
}
