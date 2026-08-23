<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Store settings
|--------------------------------------------------------------------------
*/

it('shows store settings with a QR code', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('store.edit'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Store/Settings')
            ->where('store.id', currentStore()->id)
            ->has('qrSvg')
            ->has('orderUrl')
        );
});

it('points the QR code at this store public menu', function () {
    $store = currentStore();

    $url = resolve(QrCodeService::class)->urlFor($store);
    $svg = resolve(QrCodeService::class)->svgFor($store);

    expect($url)->toBe(route('public.menu', $store->slug))
        ->and($svg)->toContain('<svg')
        ->and($svg)->toContain('</svg>');
});

it('saves store details', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('store.update'), [
            'name' => 'Kape Bagong Silang',
            'address' => '5 Katipunan Ave',
            'phone' => '0917-000-1111',
            'receipt_footer' => 'Salamat po!',
            'currency_symbol' => 'P',
            'online_ordering_enabled' => true,
        ])
        ->assertRedirect(route('store.edit'));

    expect(currentStore()->fresh()->name)->toBe('Kape Bagong Silang');
});

it('never lets the slug change, so printed codes keep working', function () {
    $admin = User::factory()->admin()->create();
    $before = currentStore()->slug;

    $this->actingAs($admin)->put(route('store.update'), [
        'name' => 'Renamed',
        'slug' => 'something-else',
        'currency_symbol' => 'P',
        'online_ordering_enabled' => false,
    ]);

    expect(currentStore()->fresh()->slug)->toBe($before);
});

it('turns customer ordering on and off from settings', function () {
    $admin = User::factory()->admin()->create();
    $store = currentStore();

    $this->actingAs($admin)->put(route('store.update'), [
        'name' => $store->name,
        'currency_symbol' => 'P',
        'online_ordering_enabled' => true,
    ]);

    $this->get(route('public.menu', $store->slug))->assertStatus(200);

    $this->actingAs($admin)->put(route('store.update'), [
        'name' => $store->name,
        'currency_symbol' => 'P',
        'online_ordering_enabled' => false,
    ]);

    $this->get(route('public.menu', $store->slug))->assertNotFound();
});

it('denies a cashier access to store settings', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get(route('store.edit'))->assertStatus(403);
});

it('only ever edits the signed-in user own store', function () {
    $other = Store::factory()->create(['name' => 'Someone Else']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('store.update'), [
        'name' => 'Mine',
        'currency_symbol' => 'P',
        'online_ordering_enabled' => false,
    ]);

    expect($other->fresh()->name)->toBe('Someone Else');
});

/*
|--------------------------------------------------------------------------
| Staff accounts
|--------------------------------------------------------------------------
*/

it('lists only this store staff', function () {
    $owner = User::factory()->owner()->create();
    User::factory()->cashier()->count(2)->create();

    $other = Store::factory()->create();
    asStore($other, fn () => User::factory()->count(5)->create());

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertInertia(fn ($page) => $page->component('Users/Index')->has('users', 3));
});

it('creates a cashier in the current store', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Paolo Cruz',
        'username' => 'paolo',
        'role' => 'cashier',
        'password' => 'sapatos-123',
        'password_confirmation' => 'sapatos-123',
    ])->assertRedirect(route('users.index'));

    $created = User::where('username', 'paolo')->firstOrFail();

    expect($created->store_id)->toBe(currentStore()->id)
        ->and($created->role->value)->toBe('cashier')
        ->and($created->is_active)->toBeTrue()
        ->and(Hash::check('sapatos-123', $created->password))->toBeTrue();
});

it('rejects a username already taken in another store', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => User::factory()->create(['username' => 'barista']));

    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Someone',
        'username' => 'barista',
        'role' => 'cashier',
        'password' => 'sapatos-123',
        'password_confirmation' => 'sapatos-123',
    ])->assertSessionHasErrors('username');
});

it('rejects a weak or unconfirmed password', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Someone',
        'username' => 'someone',
        'role' => 'cashier',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Someone',
        'username' => 'someone2',
        'role' => 'cashier',
        'password' => 'sapatos-123',
        'password_confirmation' => 'different-123',
    ])->assertSessionHasErrors('password');
});

it('stops an admin creating an owner', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'New Boss',
        'username' => 'newboss',
        'role' => 'owner',
        'password' => 'sapatos-123',
        'password_confirmation' => 'sapatos-123',
    ])->assertStatus(403);
});

it('lets an owner create another owner', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Co Owner',
        'username' => 'coowner',
        'role' => 'owner',
        'password' => 'sapatos-123',
        'password_confirmation' => 'sapatos-123',
    ])->assertSessionHasNoErrors();
});

it('keeps the password when the field is left blank', function () {
    $owner = User::factory()->owner()->create();
    $staff = User::factory()->cashier()->create();
    $before = $staff->password;

    $this->actingAs($owner)->put(route('users.update', $staff), [
        'name' => 'Renamed Person',
        'username' => $staff->username,
        'role' => 'cashier',
        'is_active' => true,
        'password' => null,
    ])->assertRedirect(route('users.index'));

    expect($staff->fresh()->name)->toBe('Renamed Person')
        ->and($staff->fresh()->password)->toBe($before);
});

it('stops someone locking themselves out', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->put(route('users.update', $owner), [
        'name' => $owner->name,
        'username' => $owner->username,
        'role' => 'cashier',
        'is_active' => true,
    ])->assertSessionHasErrors('role');

    expect($owner->fresh()->role->value)->toBe('owner');
});

it('stops someone deactivating their own account', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->delete(route('users.destroy', $owner))
        ->assertSessionHasErrors('user');

    expect($owner->fresh()->is_active)->toBeTrue();
});

it('deactivates rather than deletes, so history survives', function () {
    $owner = User::factory()->owner()->create();
    $staff = User::factory()->cashier()->create();

    $this->actingAs($owner)->delete(route('users.destroy', $staff))->assertRedirect();

    expect($staff->fresh()->is_active)->toBeFalse()
        ->and(User::withoutGlobalScopes()->find($staff->id))->not->toBeNull();
});

it('stops an admin deactivating an owner', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $owner))
        ->assertSessionHasErrors('user');

    expect($owner->fresh()->is_active)->toBeTrue();
});

it('cannot touch a user from another store', function () {
    $other = Store::factory()->create();
    $foreign = asStore($other, fn () => User::factory()->cashier()->create());

    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->delete(route('users.destroy', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->is_active)->toBeTrue();
});

it('denies a cashier access to staff management', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->get(route('users.index'))->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| A newly created account can actually work
|--------------------------------------------------------------------------
*/

it('lets a freshly created cashier sign in and reach the POS', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Paolo Cruz',
        'username' => 'paolo2',
        'role' => 'cashier',
        'password' => 'sapatos-123',
        'password_confirmation' => 'sapatos-123',
    ]);

    auth()->logout();
    session()->flush();

    $this->post('/login', ['username' => 'paolo2', 'password' => 'sapatos-123'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('pos.index'))->assertStatus(200);
});
