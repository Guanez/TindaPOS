<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(OrderItemModifier::class);
    }

    // ─── Helpers ─────────────────────────────────

    /**
     * What the barista needs to read: "Latte (16oz) + Oat milk".
     */
    public function describe(): string
    {
        $label = $this->product_name;

        if ($this->variant_name !== null) {
            $label .= " ({$this->variant_name})";
        }

        $modifiers = $this->modifiers->pluck('name')->all();

        return $modifiers === [] ? $label : $label.' + '.implode(', ', $modifiers);
    }
}
