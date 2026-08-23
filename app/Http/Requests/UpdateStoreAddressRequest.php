<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\RetiredStoreSlug;
use App\Support\StoreContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Manager role is enforced by middleware.
    }

    public function rules(): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:60',
                // Lowercase, digits and dashes only: this ends up printed on a
                // card and typed by someone whose camera will not focus.
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('stores', 'slug')->ignore(app(StoreContext::class)->id()),
                // A name any shop has ever given up stays gone.
                Rule::notIn(RetiredStoreSlug::query()->pluck('slug')->all()),
            ],
            // Typing the confirmation is the deliberate part: every printed
            // card stops working the moment this succeeds.
            'confirm' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and dashes only — it has to be readable on a printed card.',
            'slug.unique' => 'Another shop is already using that address.',
            'slug.not_in' => 'That address was used before and cannot be reissued — an old card could still send someone to it.',
            'confirm.accepted' => 'Tick the box to confirm that printed codes will stop working.',
        ];
    }
}
