<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspending a client store signs its staff out on their next request, the
 * same way deactivating one account does in EnsureUserIsActive — otherwise a
 * suspension would not bite until everyone happened to log out.
 *
 * The platform admin is exempt: suspending a shop must not lock the person
 * who suspended it out of the screen they did it from.
 */
class EnsureStoreIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return $next($request);
        }

        $store = $user->store;

        if ($store !== null && ! $store->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => 'This store is inactive. Please contact support.']);
        }

        return $next($request);
    }
}
