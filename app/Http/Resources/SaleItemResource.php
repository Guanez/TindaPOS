<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'selling_price' => $this->selling_price,
            'quantity' => $this->quantity,
            'line_total' => $this->line_total,
            // cost_price only for managers (profit visibility)
            'cost_price' => $this->when($request->user()?->isManager(), $this->cost_price),
        ];
    }
}
