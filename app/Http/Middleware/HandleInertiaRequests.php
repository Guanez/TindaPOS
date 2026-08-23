<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role->value,
                    'is_manager' => $request->user()->isManager(),
                    'is_owner' => $request->user()->isOwner(),
                ] : null,
            ],
            // The shop the signed-in user works for. Shared rather than passed
            // per page because receipts, headings and the QR link all need it,
            // and it never changes within a session.
            'store' => fn () => $this->currentStore(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'sale' => fn () => $request->session()->get('sale'),
            ],
        ];
    }

    /**
     * Null for a platform owner, who is not acting for any one shop.
     *
     * @return array<string, mixed>|null
     */
    private function currentStore(): ?array
    {
        $storeId = app(StoreContext::class)->id();

        if ($storeId === null) {
            return null;
        }

        $store = Store::query()->find($storeId);

        return $store === null ? null : [
            'name' => $store->name,
            'address' => $store->address,
            'phone' => $store->phone,
            'receipt_footer' => $store->receipt_footer,
            'currency_symbol' => $store->currency_symbol,
            'type' => $store->type,
        ];
    }
}
