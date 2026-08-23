<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\Impersonation;
use App\Support\StoreContext;
use App\Support\StoreVocabulary;
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
     * Memo for currentStore(). `false` means "not looked up yet", which null
     * cannot express here — a platform admin genuinely resolves to null.
     *
     * @var array<string, mixed>|null|false
     */
    private array|null|false $resolvedStore = false;

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
                    'is_super_admin' => $request->user()->isSuperAdmin(),
                ] : null,
            ],
            // Present only for a platform admin. `store` alone cannot carry
            // this: while they are standing inside a client shop it looks
            // exactly like that shop's own staff, which is the point — and
            // also exactly why the banner has to come from somewhere else.
            'platform' => fn () => $this->platformState($request),
            // The shop the signed-in user works for. Shared rather than passed
            // per page because receipts, headings and the QR link all need it,
            // and it never changes within a session.
            'store' => fn () => $this->currentStore(),
            // The words this shop uses for its own things — a cafe has a Menu
            // where a sari-sari store has Inventory. Keyed off the store in
            // context, so stepping into a client cafe switches the wording to
            // theirs, which is the whole point of standing inside it.
            'words' => fn () => StoreVocabulary::for($this->currentStore()['type'] ?? null),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'sale' => fn () => $request->session()->get('sale'),
            ],
        ];
    }

    /**
     * What the platform admin is currently doing, or null for everyone else.
     *
     * @return array<string, mixed>|null
     */
    private function platformState(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            return null;
        }

        $acting = app(Impersonation::class)->store();

        return [
            'acting_as' => $acting === null ? null : [
                'id' => $acting->id,
                'name' => $acting->name,
            ],
        ];
    }

    /**
     * Null for a platform admin, who is not acting for any one shop.
     *
     * @return array<string, mixed>|null
     */
    private function currentStore(): ?array
    {
        // Memoised: two shared props read this now, and without the cache
        // every Inertia response would look the same store up twice.
        if ($this->resolvedStore !== false) {
            return $this->resolvedStore;
        }

        $storeId = app(StoreContext::class)->id();

        if ($storeId === null) {
            return $this->resolvedStore = null;
        }

        $store = Store::query()->find($storeId);

        return $this->resolvedStore = $store === null ? null : [
            'name' => $store->name,
            'address' => $store->address,
            'phone' => $store->phone,
            'receipt_footer' => $store->receipt_footer,
            'currency_symbol' => $store->currency_symbol,
            'type' => $store->type,
        ];
    }
}
