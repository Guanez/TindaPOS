<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreContext
{
    public function __construct(
        private readonly StoreContext $context
    ) {}

    /**
     * Pin the request to the signed-in user's store, so every subsequent query
     * is confined to it. A platform owner has no store and stays unscoped.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $storeId = $request->user()?->store_id;

        if ($storeId !== null) {
            $this->context->set($storeId);
        }

        return $next($request);
    }
}
