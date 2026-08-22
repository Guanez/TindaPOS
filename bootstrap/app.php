<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidModifierException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\InvalidStockException;
use App\Exceptions\ProductInactiveException;
use App\Exceptions\ProductUnavailableException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolvePublicStore;
use App\Http\Middleware\ResolveStoreContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ResolveStoreContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            'active' => EnsureUserIsActive::class,
            'public.store' => ResolvePublicStore::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Render domain exceptions as 422 Unprocessable Entity
        $exceptions->renderable(function (InsufficientStockException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        });

        $exceptions->renderable(function (ProductInactiveException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        });

        $exceptions->renderable(function (ProductUnavailableException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        });

        $exceptions->renderable(function (InvalidModifierException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        });

        // Usually two taps on the same queue button.
        $exceptions->renderable(function (InvalidOrderTransitionException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        });

        $exceptions->renderable(function (SaleAlreadyVoidedException $e) {
            return back()->withErrors(['void' => $e->getMessage()]);
        });

        $exceptions->renderable(function (InvalidStockException $e) {
            return back()->withErrors(['restock' => $e->getMessage()]);
        });
    })->create();
