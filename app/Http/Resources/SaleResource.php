<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sale resource — consistent output for sale records.
 */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'user_id' => $this->user_id,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'item_count' => $this->item_count,
            'payment_method' => $this->payment_method,
            'cash_received' => $this->cash_received,
            'change_amount' => $this->change_amount,
            'status' => $this->status,
            'void_reason' => $this->when($this->status === 'voided', $this->void_reason),
            'voided_at' => $this->when($this->status === 'voided', $this->voided_at),
            'voided_by_user' => $this->when(
                $this->status === 'voided',
                fn () => $this->whenLoaded('voidedByUser', fn () => [
                    'id' => $this->voidedByUser->id,
                    'name' => $this->voidedByUser->name,
                ]),
            ),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
