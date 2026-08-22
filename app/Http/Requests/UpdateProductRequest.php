<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\StoreContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

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
            'sku' => ['sometimes', 'string', 'max:50', $this->uniqueInStore('sku', $productId)],
            'barcode' => ['nullable', 'string', 'max:50', $this->uniqueInStore('barcode', $productId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'cost_price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'selling_price' => ['sometimes', 'numeric', 'min:0', 'gte:cost_price', 'max:999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:99999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_available' => ['sometimes', 'boolean'],
            'variants' => ['sometimes', 'array', 'max:20'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:60'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'modifier_group_ids' => ['sometimes', 'array', 'max:20'],
            'modifier_group_ids.*' => ['integer'],
        ];
    }

    /**
     * SKUs and barcodes only have to be unique within the shop that uses them.
     */
    private function uniqueInStore(string $column, mixed $ignore): Unique
    {
        return Rule::unique('products', $column)
            ->where('store_id', app(StoreContext::class)->id())
            ->ignore($ignore);
    }

    public function messages(): array
    {
        return [
            'selling_price.gte' => 'Selling price must be greater than or equal to cost price.',
        ];
    }
}
