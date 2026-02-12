<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth is handled by middleware
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'payment_method' => ['required', 'in:cash,gcash,maya,card,other'],
            'cash_received' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Cart is empty. Add at least one product.',
            'items.min' => 'Cart is empty. Add at least one product.',
            'items.max' => 'Too many items in cart. Maximum is 100.',
            'items.*.product_id.exists' => 'One of the products no longer exists.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.quantity.max' => 'Quantity cannot exceed 9,999.',
            'payment_method.in' => 'Invalid payment method.',
            'discount.max' => 'Discount amount is too large.',
        ];
    }
}
