<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop using TindaPOS — a sari-sari store or a cafe.
 *
 * Stores are not themselves tenant data: this is the table the tenancy is
 * built on, so it carries no store scope.
 */
class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'address',
        'phone',
        'receipt_footer',
        'currency_symbol',
        'qr_token',
        'online_ordering_enabled',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'online_ordering_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ───────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // ─── Scopes ──────────────────────────────────

    /**
     * @param  Builder<Store>  $query
     * @return Builder<Store>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ─── Helpers ─────────────────────────────────

    public function isCafe(): bool
    {
        return $this->type === 'cafe';
    }
}
