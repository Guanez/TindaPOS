<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;

/**
 * The platform admin — the account that runs TindaPOS for other people's
 * shops rather than working in one.
 */

/*
|--------------------------------------------------------------------------
| Who counts as a platform admin
|--------------------------------------------------------------------------
| Authority is the role AND the absent store, never either alone. These two
| are the whole reason the check is not simply `store_id === null`.
*/

it('treats a super admin with no store as a platform admin', function () {
    $admin = User::factory()->superAdmin()->create();

    expect($admin->isSuperAdmin())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('platform.stores.index'))
        ->assertOk();
});

it('denies the platform to a storeless user who is merely an owner', function () {
    // The shape a bad migration or a botched store delete would leave behind.
    $stray = User::factory()->owner()->create();
    $stray->forceFill(['store_id' => null])->save();

    expect($stray->isSuperAdmin())->toBeFalse();

    $this->actingAs($stray)
        ->get(route('platform.stores.index'))
        ->assertForbidden();
});

it('denies the platform to a super admin who is pinned to a store', function () {
    $pinned = User::factory()->create([
        'role' => UserRole::SuperAdmin,
    ]);

    expect($pinned->store_id)->not->toBeNull()
        ->and($pinned->isSuperAdmin())->toBeFalse()
        ->and($pinned->isMisconfiguredSuperAdmin())->toBeTrue();

    $this->actingAs($pinned)
        ->get(route('platform.stores.index'))
        ->assertForbidden();
});

it('keeps ordinary store staff out of the platform', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get(route('platform.stores.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->cashier()->create())
        ->get(route('platform.stores.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Signing in
|--------------------------------------------------------------------------
*/

it('lands a platform admin on the console rather than a shop dashboard', function () {
    $admin = User::factory()->superAdmin()->create([
        'username' => 'boss',
        // The `hashed` cast hashes on set — passing bcrypt() here would
        // hash it twice and the login would silently never match.
        'password' => 'secret-password',
    ]);

    // A real login request has no store in context — nobody is signed in yet
    // when ResolveStoreContext runs — so the lookup is unscoped and finds a
    // storeless account. The suite's beforeEach pins one, which would scope
    // the user provider's query and hide this admin from its own login.
    app(StoreContext::class)->forget();

    $this->post(route('login'), [
        'username' => 'boss',
        'password' => 'secret-password',
    ])->assertRedirect(route('platform.stores.index', absolute: false));
});

it('bounces a platform admin out of the shop screens until they pick a shop', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.stores.index'));
});

it('gives a platform admin no store branding', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('platform.stores.index'))
        ->assertInertia(fn ($page) => $page
            ->where('store', null)
            ->where('platform.acting_as', null)
        );
});

/*
|--------------------------------------------------------------------------
| Seeing across every client
|--------------------------------------------------------------------------
*/

it('lists every store on the platform', function () {
    // Counted rather than assumed: the migration that creates the stores
    // table also inserts the shop this app was first set up for, so the
    // table is never empty at the start of a test.
    $existing = Store::query()->count();
    $existingActive = Store::query()->where('is_active', true)->count();

    $other = Store::factory()->cafe()->create(['name' => 'Kape Test']);
    $suspended = Store::factory()->create(['name' => 'Lapsed Client', 'is_active' => false]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('platform.stores.index'))
        ->assertInertia(fn ($page) => $page
            ->has('stores', $existing + 2)
            ->where('totals.stores', $existing + 2)
            ->where('totals.active', $existingActive + 1)
        )
        ->assertSee($other->name)
        ->assertSee($suspended->name);
});

it('counts each store staff against that store only', function () {
    $mine = currentStore();
    User::factory()->count(2)->create();

    $other = Store::factory()->create();
    asStore($other, fn () => User::factory()->count(4)->create());

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('platform.stores.index'))
        ->assertInertia(function ($page) use ($mine, $other) {
            $byId = collect($page->toArray()['props']['stores'])->keyBy('id');

            expect($byId[$mine->id]['staff_count'])->toBe(2)
                ->and($byId[$other->id]['staff_count'])->toBe(4);
        });
});

it('never shows the platform admin in a shop staff list', function () {
    $admin = User::factory()->superAdmin()->create();
    $owner = User::factory()->owner()->create();

    // The store's own screen, seen by the store's own owner.
    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertInertia(fn ($page) => $page->has('users', 1));

    expect(User::query()->pluck('id')->all())->not->toContain($admin->id);
});

/*
|--------------------------------------------------------------------------
| Taking on a client
|--------------------------------------------------------------------------
*/

it('creates a store and its owner together', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('platform.stores.store'), [
            'name' => 'Panaderia Uno',
            'slug' => 'panaderia-uno',
            'type' => 'cafe',
            'address' => '5 Mabini St',
            'phone' => '0917-000-1111',
            'currency_symbol' => 'P',
            'owner_name' => 'Rosa Diaz',
            'owner_username' => 'rosa_diaz',
            'owner_email' => 'rosa@panaderia.ph',
            'owner_password' => 'bakery-secret',
            'owner_password_confirmation' => 'bakery-secret',
        ])
        ->assertRedirect(route('platform.stores.index'));

    $store = Store::query()->where('slug', 'panaderia-uno')->sole();

    expect($store->is_active)->toBeTrue()
        ->and($store->online_ordering_enabled)->toBeFalse();

    // The owner has to land in the new shop, not in whatever store happened
    // to be in context — the trap BelongsToStore sets on a null store_id.
    $owner = User::query()->where('username', 'rosa_diaz')->sole();

    expect($owner->store_id)->toBe($store->id)
        ->and($owner->role)->toBe(UserRole::Owner);
});

it('rejects a duplicate ordering slug', function () {
    $taken = Store::factory()->create(['slug' => 'taken-slug']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('platform.stores.store'), [
            'name' => 'Copycat',
            'slug' => 'taken-slug',
            'type' => 'cafe',
            'currency_symbol' => 'P',
            'owner_name' => 'Someone',
            'owner_username' => 'someone_new',
            'owner_password' => 'another-secret',
            'owner_password_confirmation' => 'another-secret',
        ])
        ->assertSessionHasErrors('slug');

    expect(Store::query()->where('slug', 'taken-slug')->count())->toBe(1);
});

it('will not let a shop owner mint a platform admin', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->post(route('users.store'), [
            'name' => 'Sneaky',
            'username' => 'sneaky',
            'role' => 'super_admin',
            'password' => 'sneaky-secret',
            'password_confirmation' => 'sneaky-secret',
        ])
        ->assertSessionHasErrors('role');

    expect(User::query()->where('username', 'sneaky')->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Stepping into a client's shop
|--------------------------------------------------------------------------
*/

it('scopes the whole app to the store it steps into', function () {
    $admin = User::factory()->superAdmin()->create();

    $mine = currentStore();
    Product::factory()->active()->count(2)->create();

    $other = Store::factory()->create();
    asStore($other, fn () => Product::factory()->active()->count(5)->create());

    $this->actingAs($admin)
        ->post(route('platform.stores.enter', $other))
        ->assertRedirect(route('dashboard'));

    // Inside that shop the app behaves exactly as it does for its own staff.
    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 5)
            ->where('store.name', $other->name)
            ->where('platform.acting_as.name', $other->name)
        );
});

it('takes on the vocabulary of the shop it steps into', function () {
    // VocabularyTest pins the words for a shop's own staff. This pins the
    // half only impersonation can reach: a platform admin has no store type
    // of their own, so the wording has to follow the shop they are standing
    // in and change again when they step into a different one.
    $admin = User::factory()->superAdmin()->create();

    $cafe = Store::factory()->cafe()->create();
    $sariSari = Store::factory()->create(['type' => 'sari_sari']);

    $this->actingAs($admin)->post(route('platform.stores.enter', $cafe));

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page->where('words.catalogue', 'Menu'));

    $this->actingAs($admin)->post(route('platform.stores.enter', $sariSari));

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page->where('words.catalogue', 'Inventory'));
});

it('falls back to retail words on the platform screens', function () {
    // No store in context at all — the neutral set, not an empty payload.
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('platform.stores.index'))
        ->assertInertia(fn ($page) => $page
            ->where('store', null)
            ->where('words.catalogue', 'Inventory')
        );
});

it('outranks every role the shop itself can hand out', function () {
    $admin = User::factory()->superAdmin()->create();
    $store = currentStore();

    $this->actingAs($admin)->post(route('platform.stores.enter', $store));

    // Reports are role:owner,admin — and the platform admin holds neither.
    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk();
});

it('returns to the console on leaving a shop', function () {
    $admin = User::factory()->superAdmin()->create();
    $store = currentStore();

    $this->actingAs($admin)->post(route('platform.stores.enter', $store));

    $this->actingAs($admin)
        ->post(route('platform.leave'))
        ->assertRedirect(route('platform.stores.index'));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.stores.index'));
});

it('refuses to step into a suspended store', function () {
    $suspended = Store::factory()->create(['is_active' => false]);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('platform.stores.enter', $suspended))
        ->assertRedirect(route('platform.stores.index'))
        ->assertSessionHas('error');
});

/*
|--------------------------------------------------------------------------
| Suspending a client
|--------------------------------------------------------------------------
*/

it('suspends and reactivates a store', function () {
    $admin = User::factory()->superAdmin()->create();
    $store = currentStore();

    $this->actingAs($admin)->post(route('platform.stores.suspension', $store));
    expect($store->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post(route('platform.stores.suspension', $store));
    expect($store->fresh()->is_active)->toBeTrue();
});

it('turns away the staff of a suspended store at login', function () {
    $store = currentStore();
    User::factory()->owner()->create([
        'username' => 'locked_out',
        'password' => 'shop-secret',
    ]);

    $store->update(['is_active' => false]);

    app(StoreContext::class)->forget();

    // Asserted on the message, not merely on "some error": a scoping slip
    // would fail this login with "credentials do not match" and otherwise
    // look identical to a suspension working correctly.
    $this->post(route('login'), [
        'username' => 'locked_out',
        'password' => 'shop-secret',
    ])->assertSessionHasErrors([
        'username' => 'This store is inactive. Please contact support.',
    ]);

    $this->assertGuest();
});

it('signs out staff already inside a store the moment it is suspended', function () {
    $store = currentStore();
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get(route('dashboard'))->assertOk();

    $store->update(['is_active' => false]);

    // fresh(): the first request left this instance holding a loaded `store`
    // relation from before the suspension. A real second request resolves the
    // user from the database and would never see that stale copy.
    $this->actingAs($cashier->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('takes a suspended store QR menu offline', function () {
    $store = Store::factory()->withOnlineOrdering()->create();

    $this->get(route('public.menu', $store->slug))->assertOk();

    $store->update(['is_active' => false]);

    $this->get(route('public.menu', $store->slug))->assertNotFound();
});

it('drops the platform admin out of a shop it suspends from inside', function () {
    $admin = User::factory()->superAdmin()->create();
    $store = currentStore();

    $this->actingAs($admin)->post(route('platform.stores.enter', $store));
    $this->actingAs($admin)->post(route('platform.stores.suspension', $store));

    // No longer standing in it, so the shop screens bounce them again.
    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.stores.index'));
});

it('leaves the platform admin able to work after suspending a store', function () {
    $admin = User::factory()->superAdmin()->create();
    $store = currentStore();

    $store->update(['is_active' => false]);

    // EnsureStoreIsActive must not catch the person who pulled the lever.
    $this->actingAs($admin)
        ->get(route('platform.stores.index'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Tenancy still holds underneath
|--------------------------------------------------------------------------
*/

it('does not let a shop owner reach another shop through the platform routes', function () {
    $other = Store::factory()->create();

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('platform.stores.enter', $other))
        ->assertForbidden();

    $this->actingAs(User::factory()->owner()->create())
        ->post(route('platform.stores.suspension', $other))
        ->assertForbidden();

    expect($other->fresh()->is_active)->toBeTrue();
});

it('keeps a platform admin unscoped only while outside a shop', function () {
    $admin = User::factory()->superAdmin()->create();

    app(StoreContext::class)->forget();
    Store::factory()->create();

    expect($admin->isSuperAdmin())->toBeTrue()
        ->and(Store::query()->count())->toBeGreaterThan(1);
});
