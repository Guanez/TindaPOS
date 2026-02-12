<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Manager role is enforced by middleware
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required when voiding a sale.',
            'reason.min' => 'Please provide a more descriptive reason (at least 3 characters).',
        ];
    }
}
