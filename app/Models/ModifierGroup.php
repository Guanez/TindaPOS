<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A choice offered alongside a product — "Milk", "Extras".
 */
class ModifierGroup extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'min_select',
        'max_select',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_select' => 'integer',
            'max_select' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_group');
    }

    // ─── Scopes ──────────────────────────────────

    /**
     * @param  Builder<ModifierGroup>  $query
     * @return Builder<ModifierGroup>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ─────────────────────────────────

    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }
}
