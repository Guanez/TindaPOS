<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;

function orders(): OrderService
{
    return resolve(OrderService::class);
}

/*
|--------------------------------------------------------------------------
| Placing
|--------------------------------------------------------------------------
*/

it('places an order without touching stock', function () {
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 120]);

    $order = orders()->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'customer_name' => 'Ana',
    ]);

    expect($order->status)->toBe(OrderStatus::Placed)
        ->and((float) $order->total)->toBe(240.0)
        ->and($order->item_count)->toBe(2)
        ->and($order->queue_number)->toBe(1)
        ->and($order->token)->toHaveLength(40)
        // Nothing is reserved: preparation starts at the till.
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and(Sale::count())->toBe(0);
});

it('prices an order from the menu, not from the payload', function () {
    $product = Product::factory()->active()->create(['track_stock' => false, 'selling_price' => 120]);
    $large = ProductVariant::factory()->for($product)->priced(170)->create(['name' => '16oz']);

    $group = ModifierGroup::factory()->create(['name' => 'Milk']);
    $product->modifierGroups()->attach($group);
    $oat = Modifier::factory()->for($group, 'group')->costing(30)->create(['name' => 'Oat milk']);

    $order = orders()->place(currentStore(), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'variant_id' => $large->id,
            'modifier_ids' => [$oat->id],
            'unit_price' => 1,      // ignored
            'line_total' => 2,      // ignored
        ]],
    ]);

    $item = $order->items->first();

    expect((float) $order->total)->toBe(400.0)
        ->and((float) $item->unit_price)->toBe(200.0)
        ->and($item->describe())->toBe($product->name.' (16oz) + Oat milk');
});

it('numbers the queue sequentially per day', function () {
    $product = Product::factory()->active()->create(['track_stock' => false]);

    $numbers = collect(range(1, 3))->map(fn () => orders()->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->queue_number);

    expect($numbers->all())->toBe([1, 2, 3]);
});

it('restarts queue numbers the next day', function () {
    $product = Product::factory()->active()->create(['track_stock' => false]);

    orders()->place(currentStore(), ['items' => [['product_id' => $product->id, 'quantity' => 1]]]);

    $this->travel(1)->days();

    $tomorrow = orders()->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    expect($tomorrow->queue_number)->toBe(1);
});

it('refuses to place an order for a sold out item', function () {
    $product = Product::factory()->active()->withStock(1)->create();

    expect(fn () => orders()->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 5]],
    ]))->toThrow(InsufficientStockException::class);

    expect(Order::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Settling — the moment an order becomes a sale
|--------------------------------------------------------------------------
*/

it('creates a sale and moves stock when settled', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 120]);

    $order = orders()->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    $settled = orders()->settle($order, $cashier, ['payment_method' => 'cash', 'cash_received' => 500]);

    expect($settled->status)->toBe(OrderStatus::Paid)
        ->and($settled->sale_id)->not->toBeNull()
        ->and($settled->cashier_id)->toBe($cashier->id)
        ->and($settled->paid_at)->not->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(8)
        ->and(Sale::count())->toBe(1)
        ->and((float) $settled->sale->total)->toBe(240.0);
});

it('settles only once when the button is double-tapped', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    $order = orders()->place(currentStore(), [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    orders()->settle($order, $cashier);

    expect(fn () => orders()->settle($order->fresh(), $cashier))
        ->toThrow(InvalidOrderTransitionException::class);

    expect(Sale::count())->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe(8);
});

it('carries sizes and add-ons through to the sale', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->create(['track_stock' => false, 'selling_price' => 120]);
    $large = ProductVariant::factory()->for($product)->priced(170)->create(['name' => '16oz']);

    $group = ModifierGroup::factory()->create(['name' => 'Milk']);
    $product->modifierGroups()->attach($group);
    $oat = Modifier::factory()->for($group, 'group')->costing(30)->create(['name' => 'Oat milk']);

    $order = orders()->place(currentStore(), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $large->id,
            'modifier_ids' => [$oat->id],
        ]],
    ]);

    $settled = orders()->settle($order, $cashier);
    $saleItem = $settled->sale->items->first();

    expect($saleItem->variant_name)->toBe('16oz')
        ->and($saleItem->modifiers)->toHaveCount(1)
        ->and((float) $saleItem->selling_price)->toBe(200.0);
});

/*
|--------------------------------------------------------------------------
| The state machine
|--------------------------------------------------------------------------
*/

it('cannot ready an order nobody has paid for', function () {
    $order = Order::factory()->create();

    expect(fn () => orders()->markReady($order))
        ->toThrow(InvalidOrderTransitionException::class);
});

it('cannot settle a rejected order', function () {
    $cashier = User::factory()->cashier()->create();
    $order = Order::factory()->rejected()->create();

    expect(fn () => orders()->settle($order, $cashier))
        ->toThrow(InvalidOrderTransitionException::class);
});

it('cannot reject an order already paid for', function () {
    $user = User::factory()->admin()->create();
    $order = Order::factory()->paid()->create();

    expect(fn () => orders()->reject($order, $user, 'Changed my mind'))
        ->toThrow(InvalidOrderTransitionException::class);
});

it('walks paid to ready to collected', function () {
    $order = Order::factory()->paid()->create();

    $ready = orders()->markReady($order);
    expect($ready->status)->toBe(OrderStatus::Ready)
        ->and($ready->ready_at)->not->toBeNull();

    $collected = orders()->markCollected($ready);
    expect($collected->status)->toBe(OrderStatus::Collected)
        ->and($collected->collected_at)->not->toBeNull();
});

it('allows handing over a paid order with nothing to prepare', function () {
    $order = Order::factory()->paid()->create();

    expect(orders()->markCollected($order)->status)->toBe(OrderStatus::Collected);
});

it('records who rejected an order and why', function () {
    $admin = User::factory()->admin()->create();
    $order = Order::factory()->create();

    $rejected = orders()->reject($order, $admin, 'Kitchen closing early');

    expect($rejected->status)->toBe(OrderStatus::Rejected)
        ->and($rejected->reject_reason)->toBe('Kitchen closing early')
        ->and($rejected->rejected_by)->toBe($admin->id);
});

/*
|--------------------------------------------------------------------------
| Expiry
|--------------------------------------------------------------------------
*/

it('expires orders nobody came to pay for', function () {
    $stale = Order::factory()->placedAt(now()->subMinutes(45))->create();
    $recent = Order::factory()->placedAt(now()->subMinutes(5))->create();

    $expired = orders()->expireStale(20);

    expect($expired)->toBe(1)
        ->and($stale->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($recent->fresh()->status)->toBe(OrderStatus::Placed);
});

it('leaves paid orders alone however long they sit', function () {
    $order = Order::factory()->paid()->placedAt(now()->subHours(3))->create();

    orders()->expireStale(20);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('expires across every store at once', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Order::factory()->placedAt(now()->subHour())->create());
    Order::factory()->placedAt(now()->subHour())->create();

    // A scheduled command runs with no store in context and must sweep all.
    expect(orders()->expireStale(20))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Tenancy
|--------------------------------------------------------------------------
*/

it('hides another store orders', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Order::factory()->count(3)->create());
    Order::factory()->create();

    expect(Order::query()->count())->toBe(1);
});
