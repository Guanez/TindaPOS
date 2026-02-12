<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| POS Terminal
|--------------------------------------------------------------------------
*/

it('shows POS page with products and categories', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $category = Category::factory()->create();
    Product::factory()->active()->for($category)->count(5)->create();

    // Act & Assert
    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('POS/Index')
            ->has('products.data', 5)
            ->has('categories', 1)
        );
});

it('only shows active products in POS', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    Product::factory()->active()->count(3)->create();
    Product::factory()->inactive()->count(2)->create();

    // Act & Assert
    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 3)
        );
});

it('processes checkout with valid data', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->priced(50, 100)->withStock(20)->create();

    // Act
    $response = $this->actingAs($cashier)
        ->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            'discount' => 0,
            'payment_method' => 'cash',
            'cash_received' => 250,
        ]);

    // Assert
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('sales', [
        'user_id' => $cashier->id,
        'status' => 'completed',
        'payment_method' => 'cash',
    ]);

    expect($product->fresh()->stock_quantity)->toBe(18);
});

it('rejects checkout with empty cart', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->post(route('pos.checkout'), [
            'items' => [],
            'payment_method' => 'cash',
        ])
        ->assertSessionHasErrors('items');
});

it('rejects checkout with invalid payment method', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    $this->actingAs($cashier)
        ->post(route('pos.checkout'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
            'payment_method' => 'bitcoin', // invalid
        ])
        ->assertSessionHasErrors('payment_method');
});

it('requires authentication for POS', function () {
    $this->get(route('pos.index'))
        ->assertRedirect(route('login'));

    $this->post(route('pos.checkout'))
        ->assertRedirect(route('login'));
});
