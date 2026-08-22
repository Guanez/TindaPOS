<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Architecture Tests — TindaPOS
|--------------------------------------------------------------------------
| Enforce codebase conventions and structural constraints.
*/

arch('all PHP files use strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('models extend Eloquent Model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring([
        'App\Models\User',
        'App\Models\Concerns',
        'App\Models\Scopes',
    ]);

/*
|--------------------------------------------------------------------------
| Tenancy
|--------------------------------------------------------------------------
| Store scoping comes from exactly one trait. A tenant model that forgets it
| would silently serve one cafe's data to another, so membership of this set
| is asserted rather than trusted.
*/

arch('tenant models are scoped to a store')
    ->expect([
        'App\Models\Category',
        'App\Models\Product',
        'App\Models\Sale',
        'App\Models\StockLog',
        'App\Models\User',
    ])
    ->toUseTrait('App\Models\Concerns\BelongsToStore');

arch('the store scope is applied through the trait, never per model')
    ->expect('App\Models\Scopes\StoreScope')
    ->toOnlyBeUsedIn('App\Models\Concerns\BelongsToStore');

arch('controllers extend base Controller')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('form requests extend FormRequest')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('exceptions extend RuntimeException')
    ->expect('App\Exceptions')
    ->toExtend('RuntimeException');

arch('enums are enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('services are not used by models')
    ->expect('App\Services')
    ->not->toBeUsedIn('App\Models');

arch('models are not controllers')
    ->expect('App\Models')
    ->not->toHaveSuffix('Controller');

arch('controllers have Controller suffix')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');
