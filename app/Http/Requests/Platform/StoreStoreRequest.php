<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Taking on a new client: the shop and the person who runs it, in one form.
 *
 * They are created together on purpose — a store with no owner account is a
 * store nobody can sign into, which is not a state worth being able to reach.
 */
class StoreStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // The slug is printed onto every QR code the shop puts on its
            // counter, so it is chosen once here and never editable after.
            'slug' => ['required', 'string', 'min:3', 'max:60', 'alpha_dash', Rule::unique('stores', 'slug')],
            'type' => ['required', Rule::in(['sari_sari', 'cafe'])],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency_symbol' => ['required', 'string', 'max:5'],

            'owner_name' => ['required', 'string', 'max:255'],
            'owner_username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'owner_password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'That URL is already taken by another store.',
            'slug.alpha_dash' => 'Use letters, numbers, dashes and underscores only.',
            'owner_username.unique' => 'That username is taken. Usernames are shared across all stores.',
        ];
    }
}
