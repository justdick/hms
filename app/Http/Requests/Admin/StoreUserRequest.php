<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:4', 'max:255', 'alpha_num', 'unique:users,username'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'exists:roles,name'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['integer', 'exists:departments,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'username.required' => 'Username is required.',
            'username.min' => 'Username must be at least 4 characters.',
            'username.alpha_num' => 'Username must contain only letters and numbers.',
            'username.unique' => 'The username has already been taken.',
            'roles.required' => 'At least one role must be assigned.',
            'roles.min' => 'At least one role must be assigned.',
            'roles.*.exists' => 'One or more selected roles are invalid.',
            'departments.*.exists' => 'One or more selected departments are invalid.',
        ];
    }
}
