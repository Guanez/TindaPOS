<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use App\Models\Order;
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
            // Which side of the counter this page is on. Every type role
            // carries a counter value and a phone value, so this one string
            // switches the entire scale — a component never asks where it is.
            'density' => $this->density($request),
            // Today's queue, in three numbers. Shared rather than owned by the
            // queue screen because an order that arrives while the cashier is
            // ringing someone up on the POS is exactly the one they must not
            // miss — the badge and the chime both read this, from any page.
            'queue' => fn () => $this->queueState($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'sale' => fn () => $request->session()->get('sale'),
            ],
        ];
    }

    /**
     * Keyed off the route rather than the page component, because the surface
     * is the thing that matters: everything under the public prefix is a
     * customer holding a phone, and everything else is staff at a terminal.
     */
    private function density(Request $request): string
    {
        return $request->routeIs('public.*') ? 'touch' : 'counter';
    }

    /**
     * Today's open orders, counted by state, plus when the newest one landed.
     *
     * One grouped aggregate rather than three counts: this is polled from
     * every staff page, and a badge is not worth three round trips a tick.
     *
     * `last_placed_at` is what the chime listens to. A count cannot answer
     * "did something new arrive" on its own — settle one order and receive
     * another inside the same poll window and the count is unchanged, which
     * is precisely the moment the shop is busy enough to need telling. A
     * timestamp that only ever moves forward on a genuine arrival can.
     *
     * Null for a customer and for a platform admin who has not stepped into
     * a shop: neither has a queue of their own.
     *
     * @return array<string, mixed>|null
     */
    private function queueState(Request $request): ?array
    {
        if ($request->user() === null || app(StoreContext::class)->id() === null) {
            return null;
        }

        // COUNT(CASE WHEN …) rather than SUM(status = …), which reads more
        // neatly but is MySQL/SQLite-only — this stays true on Postgres.
        $row = Order::query()
            ->today()
            ->open()
            ->selectRaw(
                'COUNT(CASE WHEN status = ? THEN 1 END) as awaiting,'
                .'COUNT(CASE WHEN status = ? THEN 1 END) as preparing,'
                .'COUNT(CASE WHEN status = ? THEN 1 END) as ready,'
                .'MAX(placed_at) as last_placed_at',
                [
                    OrderStatus::Placed->value,
                    OrderStatus::Paid->value,
                    OrderStatus::Ready->value,
                ]
            )
            ->first();

        return [
            'awaiting' => (int) ($row->awaiting ?? 0),
            'preparing' => (int) ($row->preparing ?? 0),
            'ready' => (int) ($row->ready ?? 0),
            // A Unix timestamp, not the raw column: the client compares this
            // for "later than last time", and comparing formatted datetime
            // strings is a bug waiting for a database that formats them
            // differently.
            'last_placed_at' => $row?->last_placed_at === null
                ? null
                : strtotime((string) $row->last_placed_at),
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
