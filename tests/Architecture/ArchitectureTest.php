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
    ->ignoring('App\Models\User');

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
