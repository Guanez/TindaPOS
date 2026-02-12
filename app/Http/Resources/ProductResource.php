<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'cost_price' => $this->when($isManager, $this->cost_price),
            'selling_price' => $this->selling_price,
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'is_favorite' => $this->is_favorite,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
