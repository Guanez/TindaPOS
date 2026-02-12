<?php

declare(strict_types=1);

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Sales History
|--------------------------------------------------------------------------
*/

it('shows sales history for authenticated users', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    Sale::factory()->completed()->for($cashier)->count(3)->create();

    // Act & Assert
    $this->actingAs($cashier)
        ->get(route('sales.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Sales/Index')
            ->has('sales.data', 3)
            ->has('filters')
        );
});

it('filters sales by status', function () {
    // Arrange
    $user = User::factory()->cashier()->create();
    Sale::factory()->completed()->for($user)->count(2)->create();
    Sale::factory()->voided()->for($user)->count(1)->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('sales.index', ['status' => 'completed']))
        ->assertInertia(fn ($page) => $page->has('sales.data', 2));
});

it('filters sales by payment method', function () {
    // Arrange
    $user = User::factory()->cashier()->create();
    Sale::factory()->cash()->for($user)->count(2)->create();
    Sale::factory()->gcash()->for($user)->count(1)->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('sales.index', ['payment_method' => 'cash']))
        ->assertInertia(fn ($page) => $page->has('sales.data', 2));
});

it('shows sale details', function () {
    // Arrange
    $user = User::factory()->cashier()->create();
    $sale = Sale::factory()->completed()->for($user)->create();
    SaleItem::factory()->for($sale)->count(2)->create();

    // Act & Assert
    $this->actingAs($user)
        ->get(route('sales.show', $sale))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('saleDetail')
        );
});

/*
|--------------------------------------------------------------------------
| Void Sale — Role-Based Access
|--------------------------------------------------------------------------
*/

it('allows owner to void a sale', function () {
    // Arrange
    $owner = User::factory()->owner()->create();
    $sale = Sale::factory()->completed()->for($owner)->create();
    SaleItem::factory()->for($sale)->create();

    // Act
    $response = $this->actingAs($owner)
        ->post(route('sales.void', $sale), [
            'reason' => 'Customer complaint',
        ]);

    // Assert
    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($sale->fresh()->status)->toBe('voided');
});

it('allows admin to void a sale', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->completed()->for($admin)->create();
    SaleItem::factory()->for($sale)->create();

    // Act & Assert
    $this->actingAs($admin)
        ->post(route('sales.void', $sale), ['reason' => 'Wrong items'])
        ->assertRedirect()
        ->assertSessionHas('success');
});

it('denies cashier from voiding a sale', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $sale = Sale::factory()->completed()->for($cashier)->create();

    // Act & Assert
    $this->actingAs($cashier)
        ->post(route('sales.void', $sale), ['reason' => 'test'])
        ->assertStatus(403);
});

it('requires reason when voiding', function () {
    // Arrange
    $owner = User::factory()->owner()->create();
    $sale = Sale::factory()->completed()->for($owner)->create();

    // Act & Assert
    $this->actingAs($owner)
        ->post(route('sales.void', $sale), ['reason' => ''])
        ->assertSessionHasErrors('reason');
});
