<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductInactiveException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLog;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| SaleService — Checkout
|--------------------------------------------------------------------------
*/

it('processes a checkout successfully', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->priced(50, 100)->withStock(20)->create();

    $data = [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 3],
        ],
        'discount' => 10,
        'payment_method' => 'cash',
        'cash_received' => 300,
    ];

    // Act
    $sale = resolve(SaleService::class)->checkout($data, $cashier);

    // Assert
    expect($sale)
        ->toBeInstanceOf(Sale::class)
        ->and((float) $sale->subtotal)->toBe(300.00)
        ->and((float) $sale->discount)->toBe(10.00)
        ->and((float) $sale->total)->toBe(290.00)
        ->and($sale->item_count)->toBe(3)
        ->and($sale->payment_method)->toBe('cash')
        ->and($sale->status)->toBe('completed');

    // Sale item created with snapshot
    expect($sale->items)->toHaveCount(1);
    $item = $sale->items->first();
    expect($item->product_name)->toBe($product->name)
        ->and((float) $item->selling_price)->toBe(100.00)
        ->and($item->quantity)->toBe(3);

    // Stock deducted
    expect($product->fresh()->stock_quantity)->toBe(17);

    // Stock log created
    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity_change' => -3,
        'stock_before' => 20,
        'stock_after' => 17,
    ]);
});

it('processes checkout with multiple products', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product1 = Product::factory()->active()->priced(10, 25)->withStock(50)->create();
    $product2 = Product::factory()->active()->priced(20, 40)->withStock(30)->create();

    $data = [
        'items' => [
            ['product_id' => $product1->id, 'quantity' => 2],
            ['product_id' => $product2->id, 'quantity' => 1],
        ],
        'discount' => 0,
        'payment_method' => 'gcash',
    ];

    // Act
    $sale = resolve(SaleService::class)->checkout($data, $cashier);

    // Assert
    expect($sale->items)->toHaveCount(2)
        ->and((float) $sale->subtotal)->toBe(90.00) // (25*2) + (40*1)
        ->and((float) $sale->total)->toBe(90.00)
        ->and($sale->item_count)->toBe(3);

    expect($product1->fresh()->stock_quantity)->toBe(48);
    expect($product2->fresh()->stock_quantity)->toBe(29);
});

it('throws exception for insufficient stock', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(2)->create();

    $data = [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 5],
        ],
        'payment_method' => 'cash',
    ];

    // Act & Assert
    expect(fn () => resolve(SaleService::class)->checkout($data, $cashier))
        ->toThrow(InsufficientStockException::class);

    // Stock unchanged
    expect($product->fresh()->stock_quantity)->toBe(2);
});

it('throws exception for inactive product', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->inactive()->withStock(100)->create();

    $data = [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'payment_method' => 'cash',
    ];

    // Act & Assert
    expect(fn () => resolve(SaleService::class)->checkout($data, $cashier))
        ->toThrow(ProductInactiveException::class);
});

it('rolls back on failure — no partial sales', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product1 = Product::factory()->active()->withStock(10)->create();
    $product2 = Product::factory()->active()->withStock(1)->create();

    $data = [
        'items' => [
            ['product_id' => $product1->id, 'quantity' => 2],
            ['product_id' => $product2->id, 'quantity' => 5], // will fail
        ],
        'payment_method' => 'cash',
    ];

    // Act
    try {
        resolve(SaleService::class)->checkout($data, $cashier);
    } catch (InsufficientStockException) {
        // expected
    }

    // Assert: no sale created, stock unchanged
    $this->assertDatabaseCount('sales', 0);
    $this->assertDatabaseCount('sale_items', 0);
    expect($product1->fresh()->stock_quantity)->toBe(10);
});

it('calculates change amount for cash payments', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->priced(50, 100)->withStock(10)->create();

    $data = [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ],
        'payment_method' => 'cash',
        'cash_received' => 200,
    ];

    // Act
    $sale = resolve(SaleService::class)->checkout($data, $cashier);

    // Assert
    expect((float) $sale->total)->toBe(100.00)
        ->and((float) $sale->cash_received)->toBe(200.00)
        ->and((float) $sale->change_amount)->toBe(100.00);
});

it('generates unique receipt numbers', function () {
    // Arrange
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(100)->create();

    $receipts = [];
    for ($i = 0; $i < 5; $i++) {
        $sale = resolve(SaleService::class)->checkout([
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ], $cashier);
        $receipts[] = $sale->receipt_number;
    }

    // All unique
    expect(array_unique($receipts))->toHaveCount(5);
});

/*
|--------------------------------------------------------------------------
| SaleService — Void Sale
|--------------------------------------------------------------------------
*/

it('voids a sale and restores stock', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->active()->withStock(10)->create();
    $sale = Sale::factory()->completed()->for($admin)->create([
        'subtotal' => 200,
        'total' => 200,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    // Act
    $voided = resolve(SaleService::class)->voidSale($sale, $admin, 'Customer refund');

    // Assert
    expect($voided->status)->toBe('voided')
        ->and($voided->void_reason)->toBe('Customer refund')
        ->and($voided->voided_by)->toBe($admin->id);

    // Stock restored
    expect($product->fresh()->stock_quantity)->toBe(15);

    // Stock log created
    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => 'void_return',
        'quantity_change' => 5,
    ]);
});

it('cannot void an already voided sale', function () {
    // Arrange
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->voided()->for($admin)->create();

    // Act & Assert
    expect(fn () => resolve(SaleService::class)->voidSale($sale, $admin, 'test'))
        ->toThrow(SaleAlreadyVoidedException::class);
});
