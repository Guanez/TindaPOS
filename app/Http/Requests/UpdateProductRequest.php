<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:50', Rule::unique('products')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products')->ignore($productId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'gte:cost_price', 'max:999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'selling_price.gte' => 'Selling price must be greater than or equal to cost price.',
        ];
    }
}
