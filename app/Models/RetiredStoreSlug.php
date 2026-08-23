<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ordering address a shop has given up.
 *
 * Kept forever and never reissued. Posters outlive the addresses printed on
 * them, and a recycled slug would send a customer holding an old card to
 * whichever shop took the name next.
 *
 * Not tenant data: it is a registry spanning every store, the same as Store
 * itself, so it deliberately carries no store scope.
 */
class RetiredStoreSlug extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug',
        'store_id',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function isTaken(string $slug): bool
    {
        return static::query()->where('slug', $slug)->exists();
    }
}
