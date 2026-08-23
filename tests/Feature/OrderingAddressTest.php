<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\RetiredStoreSlug;
use App\Models\Store;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Retiring an ordering address
|--------------------------------------------------------------------------
| The recovery path for a counter card that has been shared somewhere it
| should not have been. The old address must die, and must never come back.
*/

it('moves the shop to a new address and kills the old one', function () {
    $store = currentStore();
    $store->update(['slug' => 'kape-lokal', 'online_ordering_enabled' => true]);

    $admin = User::factory()->admin()->create();

    // The old address works right up until it does not.
    $this->get(route('public.menu', 'kape-lokal'))->assertStatus(200);

    $this->actingAs($admin)
        ->put(route('store.address'), ['slug' => 'kape-lokal-2', 'confirm' => true])
        ->assertRedirect(route('store.edit'));

    expect($store->fresh()->slug)->toBe('kape-lokal-2');

    $this->get(route('public.menu', 'kape-lokal-2'))->assertStatus(200);
    $this->get(route('public.menu', 'kape-lokal'))->assertNotFound();
});

it('records the retired address', function () {
    $store = currentStore();
    $store->update(['slug' => 'old-address']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('store.address'), [
        'slug' => 'new-address',
        'confirm' => true,
    ]);

    $retired = RetiredStoreSlug::query()->where('slug', 'old-address')->first();

    expect($retired)->not->toBeNull()
        ->and($retired->store_id)->toBe($store->id)
        ->and($retired->retired_at)->not->toBeNull();
});

it('never reissues a retired address to the same shop', function () {
    $store = currentStore();
    $store->update(['slug' => 'kape-lokal']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('store.address'), ['slug' => 'kape-lokal-2', 'confirm' => true]);

    // Changing their mind must not resurrect the address on all those posters.
    $this->actingAs($admin)
        ->put(route('store.address'), ['slug' => 'kape-lokal', 'confirm' => true])
        ->assertSessionHasErrors('slug');

    expect($store->fresh()->slug)->toBe('kape-lokal-2');
});

it('never reissues a retired address to a different shop', function () {
    // The dangerous case: a poster still on a wall would send that shop's
    // customers to whoever picked the name up next.
    $store = currentStore();
    $store->update(['slug' => 'kape-lokal']);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put(route('store.address'), ['slug' => 'kape-lokal-2', 'confirm' => true]);

    $other = Store::factory()->create();
    $otherAdmin = asStore($other, fn () => User::factory()->admin()->create());

    $this->actingAs($otherAdmin)
        ->put(route('store.address'), ['slug' => 'kape-lokal', 'confirm' => true])
        ->assertSessionHasErrors('slug');
});

it('refuses an address another shop is already using', function () {
    $other = Store::factory()->create(['slug' => 'taken-already']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('store.address'), ['slug' => 'taken-already', 'confirm' => true])
        ->assertSessionHasErrors('slug');

    expect($other->fresh()->slug)->toBe('taken-already');
});

it('will not change anything without the confirmation', function () {
    $store = currentStore();
    $before = $store->slug;

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('store.address'), ['slug' => 'brand-new-address'])
        ->assertSessionHasErrors('confirm');

    expect($store->fresh()->slug)->toBe($before)
        ->and(RetiredStoreSlug::query()->count())->toBe(0);
});

it('rejects an address that would not survive being printed', function () {
    $admin = User::factory()->admin()->create();

    foreach (['Kape Lokal', 'kape_lokal', 'ka', 'kape--lokal', '-kape', 'kape lokal'] as $bad) {
        $this->actingAs($admin)
            ->put(route('store.address'), ['slug' => $bad, 'confirm' => true])
            ->assertSessionHasErrors('slug');
    }
});

it('denies a cashier the ability to change the address', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->put(route('store.address'), ['slug' => 'anything-new', 'confirm' => true])
        ->assertStatus(403);
});

it('leaves other stores alone', function () {
    $other = Store::factory()->create(['slug' => 'someone-else']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('store.address'), ['slug' => 'my-new-address', 'confirm' => true]);

    expect($other->fresh()->slug)->toBe('someone-else');
});

it('keeps live orders reachable after the address changes', function () {
    // Orders are addressed by token, not by the shop's address, so a customer
    // already waiting is not stranded by a rotation.
    $store = currentStore();
    $store->update(['online_ordering_enabled' => true]);

    $order = Order::factory()->create(['store_id' => $store->id]);

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put(route('store.address'), ['slug' => 'rotated-away', 'confirm' => true]);

    $this->get(route('public.status', $order->token))->assertStatus(200);
});
