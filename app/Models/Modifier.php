<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One option within a modifier group — "Oat milk", "Extra shot".
 */
class Modifier extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'modifier_group_id',
        'name',
        'price_delta',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    // ─── Scopes ──────────────────────────────────

    /**
     * @param  Builder<Modifier>  $query
     * @return Builder<Modifier>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
