<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One add-on as chosen on an order line, snapshotted with what it cost.
 */
class OrderItemModifier extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_item_id',
        'modifier_id',
        'name',
        'price_delta',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
