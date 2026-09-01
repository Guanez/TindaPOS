<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| The queue count, shared with every staff page
|--------------------------------------------------------------------------
|
| The point of sharing it is that a cashier away from the queue screen still
| finds out an order arrived, so most of these assert the count somewhere
| other than the queue.
|
*/

it('shares the queue with a cashier standing on the POS', function () {
    $cashier = User::factory()->cashier()->create();

    Order::factory()->count(2)->create();        // awaiting payment
    Order::factory()->paid()->create();          // being made
    Order::factory()->ready()->count(3)->create(); // waiting to be collected

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('queue.awaiting', 2)
            ->where('queue.preparing', 1)
            ->where('queue.ready', 3)
        );
});

it('counts nothing that is already finished or from yesterday', function () {
    $cashier = User::factory()->cashier()->create();

    Order::factory()->create();
    Order::factory()->rejected()->create();
    Order::factory()->create(['status' => OrderStatus::Collected]);
    Order::factory()->create(['queue_date' => today()->subDay()]);

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('queue.awaiting', 1)
            ->where('queue.preparing', 0)
            ->where('queue.ready', 0)
        );
});

it('carries the newest arrival so the chime can tell a new order from a settled one', function () {
    $cashier = User::factory()->cashier()->create();

    $older = Order::factory()->create(['placed_at' => now()->subMinutes(10)]);
    $newest = Order::factory()->create(['placed_at' => now()->subMinute()]);

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('queue.last_placed_at', $newest->placed_at->getTimestamp())
        );

    expect($older->placed_at->getTimestamp())
        ->toBeLessThan($newest->placed_at->getTimestamp());
});

it('reports an empty queue rather than nothing at all', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('queue.awaiting', 0)
            ->where('queue.last_placed_at', null)
        );
});

it('never counts another shop\'s orders', function () {
    $other = Store::factory()->create();
    Order::factory()->count(4)->create(['store_id' => $other->id]);

    $cashier = User::factory()->cashier()->create();
    Order::factory()->create();

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page->where('queue.awaiting', 1));
});

it('gives a customer no queue at all', function () {
    $store = Store::factory()->create(['online_ordering_enabled' => true]);
    Order::factory()->create(['store_id' => $store->id]);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page->where('queue', null));
});
