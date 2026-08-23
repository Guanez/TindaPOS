<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;

/*
|--------------------------------------------------------------------------
| The type scale follows the surface
|--------------------------------------------------------------------------
| Every type role carries two sizes — one for a counter terminal, one for a
| phone held at arm's length — and a single attribute on <html> picks which
| set resolves. Get this wrong and nothing errors: the customer menu simply
| renders at cashier density and reads as cramped on the device it was
| designed for, which is the kind of bug no one reports.
*/

it('gives a customer on their phone the touch scale', function () {
    $store = Store::factory()->withOnlineOrdering()->create();
    app(StoreContext::class)->set($store->id);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page->where('density', 'touch'));
});

it('gives staff at a terminal the counter scale', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('density', 'counter'));
});

it('keeps the till on the counter scale even when the shop sells online', function () {
    // The flag that opens the customer menu says nothing about the staff
    // side. A cashier's screen is a terminal whether or not the shop takes
    // QR orders.
    currentStore()->update(['online_ordering_enabled' => true]);

    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page->where('density', 'counter'));
});

it('stamps the density onto the document so the scale resolves', function () {
    // The prop is only half the story — it has to reach the <html> element,
    // because that is what the CSS actually selects on.
    $store = Store::factory()->withOnlineOrdering()->create();
    app(StoreContext::class)->set($store->id);

    $this->get(route('public.menu', $store->slug))
        ->assertSee('data-density="touch"', false);
});
