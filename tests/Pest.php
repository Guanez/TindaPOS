<?php

declare(strict_types=1);

use App\Models\Store;
use App\Support\StoreContext;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every Feature and Unit test runs inside one store. Models fill store_id
| from the context set here, so tests rarely mention tenancy at all — which
| is the point: scoping should be invisible until it is deliberately tested.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        app(StoreContext::class)->set(Store::factory()->create()->id);
    })
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * The store the current test is acting as.
 */
function currentStore(): Store
{
    return Store::query()->findOrFail(app(StoreContext::class)->id());
}

/**
 * Run a closure as a different store — used to prove isolation.
 */
function asStore(Store $store, Closure $callback): mixed
{
    return app(StoreContext::class)->runFor($store->id, $callback);
}
