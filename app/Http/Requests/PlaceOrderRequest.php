<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The store is resolved and validated by middleware.
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            // No `exists` rules on these ids on purpose: an exists check runs
            // unscoped and would confirm another store's records exist. The
            // pricer resolves them against this store's own menu.
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:20'],
            'items.*.modifier_ids.*' => ['integer'],
            'customer_name' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Your basket is empty.',
            'items.max' => 'That is too many items for one order.',
            'items.*.quantity.max' => 'That is too many of one item.',
        ];
    }
}
