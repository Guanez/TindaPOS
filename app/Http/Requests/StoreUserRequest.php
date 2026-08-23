<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only an owner may create another owner.
        return $this->input('role') !== 'owner' || $this->user()->isOwner();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Usernames are unique across the whole system, not per store:
            // sign-in has no store selector, so two "cashier" accounts would
            // make login ambiguous.
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['required', Rule::in(UserRole::assignableByStore())],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'That username is taken. Usernames are shared across all stores.',
            'username.alpha_dash' => 'Use letters, numbers, dashes and underscores only.',
        ];
    }
}
