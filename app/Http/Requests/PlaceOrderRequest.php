<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The store is resolved and validated by middleware.
    }

    /**
     * A closed shop takes no orders.
     *
     * Checked here as well as hidden in the page, because the menu stays
     * readable after closing time — which means the place button is one
     * devtools edit and one stale tab away from being usable. The customer
     * gets the same sentence either way rather than a bare 422.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Store|null $store */
            $store = $this->attributes->get('publicStore');

            if ($store === null || $store->isOpenNow()) {
                return;
            }

            $validator->errors()->add(
                'items',
                trim(($store->nextOpening() ?? 'We are closed right now.').' — nothing has been ordered.')
            );
        });
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
