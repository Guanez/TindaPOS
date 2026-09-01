<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\ProductImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product resource — hides cost_price from non-manager roles.
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isManager = $request->user()?->isManager();

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'description' => $this->description,
            // URLs, never the stored path — the layout on disk is this
            // application's business and nothing a client should learn.
            'image_thumb_url' => ProductImageService::url($this->image_path, 'thumb'),
            'image_card_url' => ProductImageService::url($this->image_path, 'card'),
            'cost_price' => $this->when($isManager, $this->cost_price),
            'selling_price' => $this->selling_price,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'track_stock' => $this->track_stock,
            'is_favorite' => $this->is_favorite,
            'is_active' => $this->is_active,
            'is_available' => $this->is_available,
            'category' => $this->whenLoaded('category'),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'selling_price' => $variant->selling_price,
                'is_default' => $variant->is_default,
                'is_active' => $variant->is_active,
                // Spread rather than when(): a MissingValue nested inside a
                // plain array is never stripped, and would ship the cost price.
                ...($isManager ? ['cost_price' => $variant->cost_price] : []),
            ])),
            'modifier_groups' => $this->whenLoaded('modifierGroups', fn () => $this->modifierGroups->map(fn ($group) => [
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
