<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Support\StoreContext;

/**
 * Cancelling is the customer's, but only until they pay.
 *
 * After payment somebody is making it, and a paid order silently vanishing
 * from the queue is worse for the shop than a customer who has to ask.
 */
function cancellableStore(): Store
{
    $store = Store::factory()->withOnlineOrdering()->create();
    app(StoreContext::class)->set($store->id);

    return $store;
}

function placedOrder(Store $store): Order
{
    $product = Product::factory()->active()->create([
        'track_stock' => false,
        'selling_price' => 100,
    ]);

    return app(OrderService::class)->place($store, [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'customer_name' => 'Ana',
    ]);
}

it('lets a customer cancel an order they have not paid for', function () {
    $store = cancellableStore();
    $order = placedOrder($store);

    $this->post(route('public.orders.cancel', $order->token))
        ->assertRedirect(route('public.status', $order->token));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('refuses to cancel once the counter has taken payment', function () {
    $store = cancellableStore();
    $order = placedOrder($store);

    $cashier = User::factory()->cashier()->create(['store_id' => $store->id]);
    app(OrderService::class)->settle($order, $cashier, ['payment_method' => 'cash']);

    $this->post(route('public.orders.cancel', $order->token))
        ->assertSessionHasErrors('order');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('will not cancel an order twice', function () {
    $store = cancellableStore();
    $order = placedOrder($store);

    $this->post(route('public.orders.cancel', $order->token));

    $this->post(route('public.orders.cancel', $order->token))
        ->assertSessionHasErrors('order');
});

it('needs the token, not the id', function () {
    $store = cancellableStore();
    placedOrder($store);

    $this->post(route('public.orders.cancel', 'not-a-real-token'))
        ->assertNotFound();
});

/*
 * A cancelled order is finished, so it leaves the queue rather than sitting
 * there as something a barista still has to deal with.
 */
it('takes a cancelled order off the queue', function () {
    $store = cancellableStore();
    $order = placedOrder($store);

    $this->post(route('public.orders.cancel', $order->token));

    expect(Order::query()->open()->count())->toBe(0);
});
