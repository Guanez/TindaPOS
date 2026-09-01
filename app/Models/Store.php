<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop using TindaPOS — a sari-sari store or a cafe.
 *
 * Stores are not themselves tenant data: this is the table the tenancy is
 * built on, so it carries no store scope.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property bool $online_ordering_enabled
 * @property bool $is_active
 * @property array<string, mixed>|null $hours the week, cast from JSON
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
        'accent',
        'logo_path',
        'online_ordering_enabled',
        'hours',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'online_ordering_enabled' => 'boolean',
            'is_active' => 'boolean',
            'hours' => 'array',
        ];
    }

    // ─── Opening hours ───────────────────────────

    /**
     * The week, as something that can answer questions about itself.
     */
    public function openingHours(): OpeningHours
    {
        return OpeningHours::from($this->hours);
    }

    /**
     * Whether the shop is taking orders right now.
     *
     * A shop that has never set hours is always open, which is how every
     * store behaved before hours existed.
     */
    public function isOpenNow(): bool
    {
        return $this->openingHours()->isOpenAt(CarbonImmutable::now());
    }

    /**
     * "Opens tomorrow at 7:00 AM", or null if it is open now or never opens.
     */
    public function nextOpening(): ?string
    {
        return $this->openingHours()->nextOpening(CarbonImmutable::now());
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
