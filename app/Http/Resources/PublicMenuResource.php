<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a customer's phone is allowed to know about a product.
 *
 * Deliberately not ProductResource: this is the public internet, and the
 * safest way to avoid leaking cost prices, stock counts, SKUs or supplier
 * detail is to build the payload from nothing rather than to remember to
 * remove things from an existing one.
 *
 * Stock becomes a boolean. A customer needs to know whether they can order
 * it, not that the shop has three left.
 */
class PublicMenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'price_from' => $this->selling_price,
            'is_favorite' => $this->is_favorite,
            'variants' => $this->whenLoaded('variants', fn () => $this->variants
                ->where('is_active', true)
                ->values()
                ->map(fn ($variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'selling_price' => $variant->selling_price,
                    'is_default' => $variant->is_default,
                ])),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => $this->modifierGroups
                ->where('is_active', true)
                ->values()
                ->map(fn ($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'min_select' => $group->min_select,
                    'max_select' => $group->max_select,
                    'modifiers' => $group->relationLoaded('modifiers')
                        ? $group->modifiers->where('is_active', true)->values()->map(fn ($modifier) => [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'price_delta' => $modifier->price_delta,
                        ])
                        : [],
                ])),
        ];
    }
}
