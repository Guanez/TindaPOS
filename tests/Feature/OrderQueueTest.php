<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;

/*
|--------------------------------------------------------------------------
| The queue screen
|--------------------------------------------------------------------------
*/

it('shows only open orders from today', function () {
    $cashier = User::factory()->cashier()->create();

    Order::factory()->create();                                  // placed
    Order::factory()->paid()->create();                          // preparing
    Order::factory()->ready()->create();                         // ready
    Order::factory()->rejected()->create();                      // finished
    Order::factory()->create(['queue_date' => today()->subDay()]); // yesterday

    $this->actingAs($cashier)
        ->get(route('orders.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders', 3)
            ->has('recentlyFinished', 1)
        );
});

it('lets a cashier work the queue', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('orders.index'))
        ->assertStatus(200);
});

it('requires authentication for the queue', function () {
    $this->get(route('orders.index'))->assertRedirect(route('login'));
});

it('never shows another store queue', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Order::factory()->count(4)->create());

    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page->has('orders', 0));
});

/*
|--------------------------------------------------------------------------
| Taking payment
|--------------------------------------------------------------------------
*/

it('settles an order at the till', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 120]);

    $order = resolve(OrderService::class)->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($cashier)
        ->post(route('orders.settle', $order), [
            'payment_method' => 'cash',
            'cash_received' => 200,
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(Sale::count())->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe(9);
});

it('reports a double settle as an error rather than a 500', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->paid()->create();

    $this->actingAs($cashier)
        ->post(route('orders.settle', $order), ['payment_method' => 'cash'])
        ->assertSessionHasErrors('order');
});

it('rejects an invalid payment method', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->create();

    $this->actingAs($cashier)
        ->post(route('orders.settle', $order), ['payment_method' => 'bitcoin'])
        ->assertSessionHasErrors('payment_method');
});

it('cannot settle an order from another store', function () {
    $other = Store::factory()->create();
    $foreign = asStore($other, fn () => Order::factory()->create());

    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->post(route('orders.settle', $foreign), ['payment_method' => 'cash'])
        ->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| Rejecting
|--------------------------------------------------------------------------
*/

it('requires a reason to reject', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->create();

    $this->actingAs($cashier)
        ->post(route('orders.reject', $order), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($order->fresh()->status)->toBe(OrderStatus::Placed);
});

it('rejects an order with a reason the customer will see', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->create();

    $this->actingAs($cashier)
        ->post(route('orders.reject', $order), ['reason' => 'Sold out of oat milk'])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Rejected)
        ->and($order->fresh()->reject_reason)->toBe('Sold out of oat milk');
});

/*
|--------------------------------------------------------------------------
| Preparation
|--------------------------------------------------------------------------
*/

it('marks a paid order ready and then collected', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->paid()->create();

    $this->actingAs($cashier)->post(route('orders.ready', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);

    $this->actingAs($cashier)->post(route('orders.collect', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::Collected);
});

it('reports readying an unpaid order as an error', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->create();

    $this->actingAs($cashier)
        ->post(route('orders.ready', $order))
        ->assertSessionHasErrors('order');
});

/*
|--------------------------------------------------------------------------
| The scheduled sweep
|--------------------------------------------------------------------------
*/

it('expires stale orders from the command line', function () {
    Order::factory()->placedAt(now()->subHour())->create();
    Order::factory()->placedAt(now()->subMinute())->create();

    $this->artisan('orders:expire')
        ->expectsOutputToContain('Expired 1 order(s)')
        ->assertExitCode(0);
});

it('honours a custom expiry window', function () {
    Order::factory()->placedAt(now()->subMinutes(10))->create();

    $this->artisan('orders:expire --minutes=5')
        ->expectsOutputToContain('Expired 1 order(s)')
        ->assertExitCode(0);
});
