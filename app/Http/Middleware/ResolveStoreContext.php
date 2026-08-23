<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreContext
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * Pin the request to the store the signed-in user is acting for, so every
     * subsequent query is confined to it.
     *
     * For ordinary staff that is simply their own shop. A platform admin has
     * none, and is set to null unconditionally rather than left alone: null
     * has to be reached by decision, not by nothing having set a store
     * earlier in the request. While they are standing inside a client store
     * that store wins, which is what makes the rest of the application work
     * unchanged during support.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            // A store that was suspended or deleted while they were inside it
            // resolves to null, dropping them back to the platform view.
            $this->context->set($this->impersonation->store()?->id);

            return $next($request);
        }

        $this->context->set($user->store_id);

        return $next($request);
    }
}
