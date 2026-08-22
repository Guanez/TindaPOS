<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant data.
 *
 * Every tenant-scoped model gets its scoping from this one trait — never a
 * scope written per model. An architecture test asserts the membership of
 * this set, so a model added later cannot quietly opt out.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', app(StoreContext::class)->id());
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
