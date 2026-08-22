<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins an unauthenticated request to the store named in the URL.
 *
 * Every other route gets its store from the signed-in user. These do not have
 * one, and an unscoped query on a public page is the whole risk of this
 * feature — so the store is resolved here, before a controller runs, or the
 * request never reaches one.
 *
 * A store that is inactive or has not switched online ordering on is a 404,
 * not a 403: there is nothing to tell an anonymous visitor about it.
 */
class ResolvePublicStore
{
    public function __construct(
        private readonly StoreContext $context
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('storeSlug');

        $store = Store::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('online_ordering_enabled', true)
            ->first();

        if ($store === null) {
            abort(404);
        }

        $this->context->set($store->id);
        $request->attributes->set('publicStore', $store);

        return $next($request);
    }
}
