<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->input('role') !== 'owner' || $this->user()->isOwner();
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::in(['owner', 'admin', 'cashier'])],
            'is_active' => ['required', 'boolean'],
            // Left blank means "leave the password alone".
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }
}
