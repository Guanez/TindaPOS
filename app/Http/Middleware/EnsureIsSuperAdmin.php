<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the platform screens — the ones that see across every client.
 *
 * Kept separate from CheckRole because this is not a role check: it also
 * insists on the null store_id, and the two answer different questions.
 */
class EnsureIsSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            abort(403, 'This area is for platform administrators.');
        }

        return $next($request);
    }
}
