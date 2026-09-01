<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Support\StoreContext;

/**
 * "About six minutes to go" instead of "hang tight".
 *
 * The remaining minutes are computed on the server and sent as a number, not
 * as a target time for the phone to subtract from — a customer's clock can be
 * minutes out and neither they nor the shop would ever know.
 */
function estimateStore(?int $prepMinutes): Store
{
    $store = Store::factory()->withOnlineOrdering()->create(['prep_minutes' => $prepMinutes]);
    app(StoreContext::class)->set($store->id);

    return $store;
}

function paidOrderFor(Store $store)
{
    $product = Product::factory()->active()->create([
        'track_stock' => false,
        'selling_price' => 100,
    ]);

    $order = app(OrderService::class)->place($store, [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $cashier = User::factory()->cashier()->create(['store_id' => $store->id]);

    return app(OrderService::class)->settle($order, $cashier, ['payment_method' => 'cash']);
}

it('tells a waiting customer how much longer', function () {
    $store = estimateStore(8);
    $order = paidOrderFor($store);

    $this->get(route('public.status', $order->token))
        ->assertInertia(fn ($page) => $page->where('order.ready_in_minutes', 8));
});

it('says nothing when the shop has not set a preparation time', function () {
    $store = estimateStore(null);
    $order = paidOrderFor($store);

    $this->get(route('public.status', $order->token))
        ->assertInertia(fn ($page) => $page->where('order.ready_in_minutes', null));
});

it('offers no estimate before payment, because nothing has started', function () {
    $store = estimateStore(8);
    $product = Product::factory()->active()->create(['track_stock' => false]);

    $order = app(OrderService::class)->place($store, [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->get(route('public.status', $order->token))
        ->assertInertia(fn ($page) => $page->where('order.ready_in_minutes', null));
});

/*
 * An estimate that has run out reads as zero — "any moment now" — rather than
 * counting into negative numbers, which is a worse answer than none.
 */
it('floors a late order at zero rather than going negative', function () {
    $store = estimateStore(5);
    $order = paidOrderFor($store);

    $order->forceFill(['paid_at' => now()->subMinutes(30)])->save();

    $this->get(route('public.status', $order->token))
        ->assertInertia(fn ($page) => $page->where('order.ready_in_minutes', 0));
});

it('puts the preparation time on the menu too', function () {
    $store = estimateStore(8);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page->where('store.prep_minutes', 8));
});
