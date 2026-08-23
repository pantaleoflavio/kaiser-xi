<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'current_password' => ['required_with:email', 'string'],
            'theme' => ['sometimes', 'required', 'string', Rule::enum(UserTheme::class)],
            'id' => ['prohibited'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'roles' => ['prohibited'],
            'role' => ['prohibited'],
            'league_roles' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->has('email') || $validator->errors()->has('current_password')) {
                return;
            }

            if (! Hash::check((string) $this->input('current_password'), (string) $this->user()?->password)) {
                $validator->errors()->add('current_password', __('validation.current_password'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $input = [];

        if ($this->has('name')) {
            $input['name'] = trim((string) $this->input('name'));
        }

        if ($this->has('email')) {
            $input['email'] = trim((string) $this->input('email'));
        }

        $this->merge($input);
    }
}
