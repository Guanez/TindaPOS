<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Manager role is enforced by middleware.
    }

    /**
     * The slug is deliberately absent: it is printed into every QR code a
     * cafe has put out, and changing it would silently break them all.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            // Exactly six hex digits. Anything looser and the value ends
            // up inside a CSS rule, which is not a place to be relaxed.
            'accent' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['sometimes', 'image', 'mimes:png,webp,jpeg,jpg', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'online_ordering_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent.regex' => 'Pick a colour in the form #1A70F5.',
        ];
    }
}
