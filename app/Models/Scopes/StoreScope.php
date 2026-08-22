<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Confines every query to the store currently in context.
 *
 * The table name is qualified because this scope also applies inside joins
 * and whereHas subqueries, where a bare `store_id` would be ambiguous.
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = app(StoreContext::class)->id();

        if ($storeId !== null) {
            $builder->where($model->getTable().'.store_id', $storeId);
        }
    }
}
