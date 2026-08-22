<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'modifiers',
        'cost_price',
        'selling_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
            'modifiers' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    // ─── Helpers ─────────────────────────────────

    public function lineProfit(): float
    {
        return ($this->selling_price - $this->cost_price) * $this->quantity;
    }

    /**
     * What was sold, as a cashier would read it back: "Latte (16oz) + Oat milk".
     */
    public function describe(): string
    {
        $label = $this->product_name;

        if ($this->variant_name !== null) {
            $label .= " ({$this->variant_name})";
        }

        $modifiers = collect($this->modifiers ?? [])->pluck('name')->all();

        return $modifiers === [] ? $label : $label.' + '.implode(', ', $modifiers);
    }
}
