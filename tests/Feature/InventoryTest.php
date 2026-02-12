<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Inventory Management — Access Control
|--------------------------------------------------------------------------
*/

it('allows admin to access inventory', function () {
    $admin = User::factory()->admin()->create();
    Category::factory()->create();

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Inventory/Index'));
});

it('allows owner to access inventory', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('inventory.index'))
        ->assertStatus(200);
});

it('denies cashier access to inventory', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('inventory.index'))
        ->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Inventory CRUD
|--------------------------------------------------------------------------
*/

it('creates a new product', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    // Act
    $this->actingAs($admin)
        ->post(route('inventory.store'), [
            'category_id' => $category->id,
            'name' => 'Lucky Me Pancit Canton',
            'sku' => 'LM-001',
            'cost_price' => 8.50,
            'selling_price' => 12.00,
            'stock_quantity' => 100,
            'low_stock_threshold' => 20,
        ])
        ->assertRedirect(route('inventory.index'));

    // Assert
    $this->assertDatabaseHas('products', [
        'name' => 'Lucky Me Pancit Canton',
        'sku' => 'LM-001',
    ]);
});

it('validates required fields when creating product', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('inventory.store'), [])
        ->assertSessionHasErrors(['category_id', 'name', 'sku', 'cost_price', 'selling_price', 'stock_quantity']);
});

it('validates selling price >= cost price', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('inventory.store'), [
            'category_id' => $category->id,
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'cost_price' => 100,
            'selling_price' => 50, // less than cost!
            'stock_quantity' => 10,
        ])
        ->assertSessionHasErrors('selling_price');
});

it('updates a product', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create(['name' => 'Old Name']);

    // Act
    $this->actingAs($admin)
        ->put(route('inventory.update', $product), [
            'name' => 'New Name',
        ])
        ->assertRedirect(route('inventory.index'));

    // Assert
    expect($product->fresh()->name)->toBe('New Name');
});

it('deactivates a product (soft delete)', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->create();

    // Act
    $this->actingAs($admin)
        ->delete(route('inventory.destroy', $product))
        ->assertRedirect(route('inventory.index'));

    // Assert
    expect($product->fresh()->is_active)->toBeFalse();
});

it('restocks a product', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    // Act
    $this->actingAs($admin)
        ->post(route('inventory.restock'), [
            'product_id' => $product->id,
            'quantity' => 25,
            'reason' => 'Delivery arrived',
        ])
        ->assertRedirect();

    // Assert
    expect($product->fresh()->stock_quantity)->toBe(35);
});

/*
|--------------------------------------------------------------------------
| Inventory Filters
|--------------------------------------------------------------------------
*/

it('filters products by search term', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->active()->create(['name' => 'Lucky Me Noodles']);
    Product::factory()->active()->create(['name' => 'Coca Cola']);

    $this->actingAs($admin)
        ->get(route('inventory.index', ['search' => 'Lucky']))
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

it('filters products by category', function () {
    $admin = User::factory()->admin()->create();
    $cat1 = Category::factory()->create(['name' => 'Noodles']);
    $cat2 = Category::factory()->create(['name' => 'Drinks']);
    Product::factory()->active()->for($cat1)->count(3)->create();
    Product::factory()->active()->for($cat2)->count(2)->create();

    $this->actingAs($admin)
        ->get(route('inventory.index', ['category' => $cat1->id]))
        ->assertInertia(fn ($page) => $page->has('products.data', 3));
});

it('filters products by low stock', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->lowStock()->count(2)->create();
    Product::factory()->active()->withStock(100)->count(3)->create();

    $this->actingAs($admin)
        ->get(route('inventory.index', ['stock' => 'low']))
        ->assertInertia(fn ($page) => $page->has('products.data', 2));
});

/*
|--------------------------------------------------------------------------
| Stock Logs
|--------------------------------------------------------------------------
*/

it('shows stock logs page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('inventory.logs'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Inventory/Logs'));
});
