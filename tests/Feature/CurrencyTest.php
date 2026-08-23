<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;

/**
 * stores.currency_symbol was editable long before anything read it: the
 * settings form saved it, showed a success toast, and every figure on every
 * screen went on saying the peso sign regardless.
 *
 * The formatting itself lives in the client, so what is pinned here is the
 * contract the client formats against — that the chosen symbol actually
 * reaches the page. A shop sets a plain "P" because its thermal printer
 * cannot render the glyph, and that has to survive to the screen.
 */
it('carries the shop own symbol to the page', function () {
    currentStore()->update(['currency_symbol' => 'P']);

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('store.currency_symbol', 'P'));
});

it('does not leak one shop symbol into another', function () {
    currentStore()->update(['currency_symbol' => 'P']);

    $other = Store::factory()->create(['currency_symbol' => 'PHP']);
    $theirOwner = asStore($other, fn () => User::factory()->owner()->create());

    $this->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('store.currency_symbol', 'P'));

    $this->actingAs($theirOwner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('store.currency_symbol', 'PHP'));
});

it('follows the shop a platform admin steps into', function () {
    $admin = User::factory()->superAdmin()->create();
    $shop = Store::factory()->create(['currency_symbol' => 'P']);

    $this->actingAs($admin)->post(route('platform.stores.enter', $shop));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('store.currency_symbol', 'P'));
});

it('reaches the customer ordering page, which no staff member ever sees', function () {
    $shop = Store::factory()->withOnlineOrdering()->create(['currency_symbol' => 'P']);

    $this->get(route('public.menu', $shop->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('store.currency_symbol', 'P'));
});
