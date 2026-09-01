<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Today's order flow, on the dashboard
|--------------------------------------------------------------------------
*/

it('reports how long the shop is taking to make things', function () {
    $owner = User::factory()->owner()->create();

    // Six and ten minutes from payment to ready — an eight minute mean.
    Order::factory()->ready()->create([
        'paid_at' => now()->subMinutes(20),
        'ready_at' => now()->subMinutes(14),
    ]);
    Order::factory()->ready()->create([
        'paid_at' => now()->subMinutes(30),
        'ready_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('orderFlow.avg_prep_minutes', 8));
});

it('says nothing rather than zero when nothing has been made yet', function () {
    $owner = User::factory()->owner()->create();

    Order::factory()->create(); // placed, never paid

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('orderFlow.avg_prep_minutes', null));
});

it('ignores an order that was never prepared when averaging', function () {
    $owner = User::factory()->owner()->create();

    Order::factory()->ready()->create([
        'paid_at' => now()->subMinutes(10),
        'ready_at' => now()->subMinutes(6),
    ]);
    // Rejected at the till: no work was done, so it cannot describe the wait.
    Order::factory()->rejected()->create([
        'paid_at' => null,
        'ready_at' => null,
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('orderFlow.avg_prep_minutes', 4));
});

it('counts rejected and expired together as never collected', function () {
    $owner = User::factory()->owner()->create();

    Order::factory()->rejected()->create();
    Order::factory()->create(['status' => OrderStatus::Expired]);
    Order::factory()->create(['status' => OrderStatus::Collected]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('orderFlow.unfulfilled', 2)
            ->where('orderFlow.collected', 1)
        );
});

it('gives a cashier the flow figures too, since none of them are cost', function () {
    $cashier = User::factory()->cashier()->create();

    Order::factory()->create(['status' => OrderStatus::Collected]);

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('orderFlow.collected', 1)
            ->has('orderFlow.avg_prep_minutes')
        );
});

it('leaves yesterday out of it', function () {
    $owner = User::factory()->owner()->create();

    Order::factory()->create([
        'status' => OrderStatus::Collected,
        'queue_date' => today()->subDay(),
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('orderFlow.collected', 0));
});
