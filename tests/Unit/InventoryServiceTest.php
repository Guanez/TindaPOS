<?php

declare(strict_types=1);

use App\Exceptions\InvalidStockException;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| InventoryService — Restock
|--------------------------------------------------------------------------
*/

it('restocks a product and logs the change', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    // Act
    $result = resolve(InventoryService::class)->restock($product->id, 25, $admin, 'Weekly restock');

    // Assert
    expect($result->stock_quantity)->toBe(35);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'user_id' => $admin->id,
        'type' => 'restock',
        'quantity_change' => 25,
        'stock_before' => 10,
        'stock_after' => 35,
        'reason' => 'Weekly restock',
    ]);
});

it('rejects zero quantity restock', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    // Act & Assert
    expect(fn () => resolve(InventoryService::class)->restock($product->id, 0, $admin))
        ->toThrow(InvalidStockException::class, 'Restock quantity must be positive.');
});

it('rejects negative quantity restock', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    // Act & Assert
    expect(fn () => resolve(InventoryService::class)->restock($product->id, -5, $admin))
        ->toThrow(InvalidStockException::class, 'Restock quantity must be positive.');
});

/*
|--------------------------------------------------------------------------
| InventoryService — Adjust
|--------------------------------------------------------------------------
*/

it('adjusts stock positively', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(20)->create();

    // Act
    $result = resolve(InventoryService::class)->adjust($product->id, 10, $admin, 'Found extra stock');

    // Assert
    expect($result->stock_quantity)->toBe(30);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'quantity_change' => 10,
        'stock_before' => 20,
        'stock_after' => 30,
    ]);
});

it('adjusts stock negatively', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(20)->create();

    // Act
    $result = resolve(InventoryService::class)->adjust($product->id, -5, $admin, 'Damaged goods');

    // Assert
    expect($result->stock_quantity)->toBe(15);
});

it('rejects adjustment that would cause negative stock', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(5)->create();

    // Act & Assert
    expect(fn () => resolve(InventoryService::class)->adjust($product->id, -10, $admin))
        ->toThrow(InvalidStockException::class, 'negative stock');
});
