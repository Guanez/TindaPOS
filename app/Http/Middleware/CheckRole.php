<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     * Usage in routes: ->middleware('role:owner,admin')
     *
     * Account status is handled separately by EnsureUserIsActive.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! empty($roles) && ! $user->hasRole(...$roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
