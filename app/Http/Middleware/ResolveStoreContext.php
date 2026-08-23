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
        $user = $request->user();

        if ($user !== null) {
            // Set unconditionally, null included: a platform owner has no shop
            // and must end up unscoped by decision rather than by nothing
            // having set a store earlier in the request.
            $this->context->set($user->store_id);
        }

        return $next($request);
    }
}
