<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && Permissions::canManageUser($this->user(), $target);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', Rule::in(Permissions::assignableRoles($this->user()->role))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::keys())],
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
            'role.in' => 'You are not allowed to give this role.',
        ];
    }
}
