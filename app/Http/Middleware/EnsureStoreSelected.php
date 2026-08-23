<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a platform admin out of the shop screens until they have picked a
 * shop to be in.
 *
 * Without this they would still reach /inventory and friends, and because a
 * null store context applies no filter they would see every client's products
 * blended into one list — not a leak, since they may see it all anyway, but a
 * genuinely misleading screen and a dangerous one to edit from.
 */
class EnsureStoreSelected
{
    public function __construct(
        private readonly Impersonation $impersonation
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuperAdmin() && ! $this->impersonation->isActive()) {
            return redirect()->route('platform.stores.index');
        }

        return $next($request);
    }
}
