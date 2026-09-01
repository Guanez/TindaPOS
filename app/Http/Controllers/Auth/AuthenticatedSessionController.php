<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'demoCredentials' => $this->demoCredentials(),
        ]);
    }

    /**
     * The seeder's account, printed under the form so a new developer can get
     * in without reading the README.
     *
     * Decided here rather than in the page, because a `v-if` in the template
     * only hides it — the password still ships inside the bundle for anyone
     * who opens the network tab. Sent as null in every other environment, so
     * the string never leaves the server.
     *
     * Keyed off APP_ENV rather than the build mode: `npm run build` on a
     * developer's own machine should not take the hint away from them.
     */
    private function demoCredentials(): ?string
    {
        return app()->environment('local') ? 'owner / owner123' : null;
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // A platform admin has no shop to land in, and the dashboard would
        // only bounce them here anyway.
        if ($request->user()->isSuperAdmin()) {
            return redirect()->intended(route('platform.stores.index', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Invalidating the session drops any impersonation with it, so a
        // platform admin never returns to a shop they forgot they were in.
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
