<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The platform's own edit form. Wider than the shop's own settings screen —
 * it can rename a client and change their plan-ish flags — but still cannot
 * touch the slug, for the same reason nobody can: it is on their printed QR.
 */
class UpdateStoreRequest extends FormRequest
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
            'type' => ['required', Rule::in(['sari_sari', 'cafe'])],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'online_ordering_enabled' => ['required', 'boolean'],
        ];
    }
}
