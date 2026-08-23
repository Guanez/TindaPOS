<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreStoreRequest;
use App\Http\Requests\Platform\UpdateStoreRequest;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\StoreStarterKit;
use App\Support\Impersonation;
use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landlord's view: every client shop on the platform.
 *
 * Every query here runs unscoped, which is only safe because the `platform`
 * middleware has already established that the caller is a super admin with no
 * store of their own. Nothing in this controller may be reachable without it.
 */
class StoreController extends Controller
{
    public function __construct(
        private readonly Impersonation $impersonation,
        private readonly StoreContext $context,
        private readonly StoreStarterKit $starterKit,
    ) {}

    public function index(): Response
    {
        $monthToDate = $this->revenueSince(Carbon::now()->startOfMonth());

        $stores = Store::query()
            ->withCount(['users' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'type' => $store->type,
                'address' => $store->address,
                'phone' => $store->phone,
                'is_active' => $store->is_active,
                'online_ordering_enabled' => $store->online_ordering_enabled,
                'currency_symbol' => $store->currency_symbol,
                'staff_count' => $store->users_count,
                'revenue_month' => (float) ($monthToDate[$store->id] ?? 0),
                'created_at' => $store->created_at?->toDateString(),
            ]);

        return Inertia::render('Platform/Stores/Index', [
            'stores' => $stores,
            'totals' => [
                'stores' => $stores->count(),
                'active' => $stores->where('is_active', true)->count(),
                'staff' => $stores->sum('staff_count'),
                'revenue_month' => $stores->sum('revenue_month'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Platform/Stores/Create');
    }

    /**
     * A shop and its first owner, created together in one transaction — a
     * store nobody can sign into is not a state worth being able to reach.
     */
    public function store(StoreStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $store = DB::transaction(function () use ($data): Store {
            $store = Store::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'type' => $data['type'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'currency_symbol' => $data['currency_symbol'],
                'is_active' => true,
                'online_ordering_enabled' => false,
            ]);

            // BelongsToStore stamps whichever store is in context, and a
            // platform admin has none — so the new shop is named explicitly.
            $this->context->runFor($store->id, function () use ($data): void {
                User::create([
                    'name' => $data['owner_name'],
                    'username' => $data['owner_username'],
                    'email' => $data['owner_email'] ?? null,
                    'password' => $data['owner_password'],
                    'role' => UserRole::Owner,
                    'is_active' => true,
                ]);
            });

            // Categories and, for a cafe, the add-on groups every cafe needs.
            // Inside the transaction so a shop cannot come into existence
            // half-furnished. It scopes itself, so it is safe here where the
            // caller has no store of their own.
            $this->starterKit->applyTo($store);

            return $store;
        });

        return redirect()->route('platform.stores.index')
            ->with('success', "{$store->name} is set up. {$data['owner_username']} can sign in now.");
    }

    public function edit(Store $store): Response
    {
        return Inertia::render('Platform/Stores/Edit', [
            'store' => $store->only([
                'id', 'name', 'slug', 'type', 'address', 'phone',
                'currency_symbol', 'online_ordering_enabled', 'is_active',
            ]),
            'staff' => User::query()
                ->where('store_id', $store->id)
                ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role_label' => $user->role->label(),
                    'is_active' => $user->is_active,
                ]),
        ]);
    }

    public function update(UpdateStoreRequest $request, Store $store): RedirectResponse
    {
        $store->update($request->validated());

        return redirect()->route('platform.stores.index')
            ->with('success', "{$store->name} updated.");
    }

    /**
     * Suspending a client cuts both ways at once: their staff are signed out
     * on their next request by EnsureStoreIsActive, and their QR menu 404s
     * because ResolvePublicStore already refuses an inactive store.
     */
    public function toggleSuspension(Store $store): RedirectResponse
    {
        $store->update(['is_active' => ! $store->is_active]);

        // Standing inside a shop you have just suspended is a contradiction.
        if (! $store->is_active && $this->impersonation->storeId() === $store->id) {
            $this->impersonation->leave();
        }

        $message = $store->is_active
            ? "{$store->name} is active again."
            : "{$store->name} is suspended. Their staff can no longer sign in.";

        return redirect()->route('platform.stores.index')->with('success', $message);
    }

    /**
     * Step into a client's shop to help them. Everything downstream — POS,
     * inventory, reports — then works unchanged, because the store context is
     * all any of it ever consults.
     */
    public function enter(Store $store): RedirectResponse
    {
        if (! $store->is_active) {
            return redirect()->route('platform.stores.index')
                ->with('error', "{$store->name} is suspended. Reactivate it before entering.");
        }

        $this->impersonation->enter($store);

        return redirect()->route('dashboard')
            ->with('success', "You are now working inside {$store->name}.");
    }

    public function leave(): RedirectResponse
    {
        $this->impersonation->leave();

        return redirect()->route('platform.stores.index');
    }

    /**
     * Completed revenue per store since a given moment, keyed by store id.
     *
     * Runs unscoped by design — grouping by store is the entire point, and a
     * platform admin's null context is what permits it.
     *
     * @return array<int, float>
     */
    private function revenueSince(Carbon $since): array
    {
        return Sale::query()
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->groupBy('store_id')
            ->selectRaw('store_id, SUM(total) as revenue')
            ->pluck('revenue', 'store_id')
            ->map(fn ($revenue) => (float) $revenue)
            ->all();
    }
}
