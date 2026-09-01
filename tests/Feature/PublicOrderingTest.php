<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Support\StoreContext;

/**
 * A store that has switched online ordering on, made the current context.
 */
function openStore(): Store
{
    $store = Store::factory()->withOnlineOrdering()->create();
    app(StoreContext::class)->set($store->id);

    return $store;
}

/*
|--------------------------------------------------------------------------
| The feature ships dark
|--------------------------------------------------------------------------
*/

it('hides the menu of a store that has not enabled ordering', function () {
    $store = Store::factory()->create(['online_ordering_enabled' => false]);

    $this->get(route('public.menu', $store->slug))->assertNotFound();
});

it('hides the menu of a deactivated store', function () {
    $store = Store::factory()->withOnlineOrdering()->create(['is_active' => false]);

    $this->get(route('public.menu', $store->slug))->assertNotFound();
});

it('returns nothing for an unknown store', function () {
    $this->get(route('public.menu', 'no-such-cafe'))->assertNotFound();
});

it('refuses to place an order against a disabled store', function () {
    $store = Store::factory()->create(['online_ordering_enabled' => false]);

    $this->post(route('public.orders.place', $store->slug), ['items' => []])
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The menu is public, so it must say as little as possible
|--------------------------------------------------------------------------
*/

it('shows the menu without signing in', function () {
    $store = openStore();
    Product::factory()->active()->count(3)->create(['track_stock' => false]);

    $this->get(route('public.menu', $store->slug))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Public/Menu')
            ->has('products.data', 3)
            ->where('store.name', $store->name)
        );
});

it('never exposes stock, cost or sku to a customer', function () {
    $store = openStore();
    Product::factory()->active()->withStock(7)->create(['cost_price' => 40, 'sku' => 'SECRET-1']);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page
            ->missing('products.data.0.stock_quantity')
            ->missing('products.data.0.cost_price')
            ->missing('products.data.0.sku')
            ->missing('products.data.0.barcode')
            ->has('products.data.0.price_from')
        )
        ->assertDontSee('SECRET-1');
});

/*
 * Sold out is shown and marked; deactivated is gone.
 *
 * These used to be treated the same and both hidden, which meant a customer
 * looking for their usual could not tell "we ran out today" from "we stopped
 * making it" — and the shop got asked at the counter either way. `is_active`
 * is now the only line that removes something from the menu.
 *
 * Named deliberately: products are ordered by name, so this asserts on
 * position without depending on what the factory invented.
 */
it('shows sold out items but marks them unorderable', function () {
    $store = openStore();

    Product::factory()->active()->withStock(5)->create(['name' => 'Alpha']);
    Product::factory()->active()->withStock(0)->create(['name' => 'Bravo']);
    Product::factory()->active()->create(['name' => 'Charlie', 'is_available' => false]);
    Product::factory()->create(['name' => 'Delta', 'is_active' => false]);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 3)
            ->where('products.data.0.is_available', true)
            ->where('products.data.1.is_available', false)
            ->where('products.data.2.is_available', false)
        );
});

it('still refuses to sell something that is sold out', function () {
    $store = openStore();
    $product = Product::factory()->active()->withStock(0)->create();

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasErrors();
});

it('shows a made to order item even with no stock', function () {
    $store = openStore();
    Product::factory()->active()->create(['track_stock' => false, 'stock_quantity' => 0]);

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

it('never shows another store menu', function () {
    $store = openStore();
    Product::factory()->active()->withStock(5)->create();

    $other = Store::factory()->create();
    asStore($other, fn () => Product::factory()->active()->withStock(5)->count(4)->create());

    $this->get(route('public.menu', $store->slug))
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
});

/*
|--------------------------------------------------------------------------
| Placing an order
|--------------------------------------------------------------------------
*/

it('places an order and redirects to its status page', function () {
    $store = openStore();
    $product = Product::factory()->active()->create(['track_stock' => false, 'selling_price' => 120]);

    $response = $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
        'customer_name' => 'Ana',
    ]);

    $order = Order::withoutGlobalScopes()->firstOrFail();

    $response->assertRedirect(route('public.status', $order->token));

    expect($order->status)->toBe(OrderStatus::Placed)
        ->and((float) $order->total)->toBe(240.0)
        ->and($order->store_id)->toBe($store->id)
        ->and($order->customer_name)->toBe('Ana');
});

it('ignores a price sent by the customer', function () {
    $store = openStore();
    $product = Product::factory()->active()->create(['track_stock' => false, 'selling_price' => 150]);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1,
            'line_total' => 1,
            'total' => 1,
        ]],
    ]);

    expect((float) Order::withoutGlobalScopes()->firstOrFail()->total)->toBe(150.0);
});

it('prices sizes and add-ons from the menu', function () {
    $store = openStore();
    $product = Product::factory()->active()->create(['track_stock' => false, 'selling_price' => 130]);
    $large = ProductVariant::factory()->for($product)->priced(170)->create(['name' => '16oz']);

    $group = ModifierGroup::factory()->create(['name' => 'Milk']);
    $product->modifierGroups()->attach($group);
    $oat = Modifier::factory()->for($group, 'group')->costing(30)->create(['name' => 'Oat milk']);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'variant_id' => $large->id,
            'modifier_ids' => [$oat->id],
        ]],
    ]);

    expect((float) Order::withoutGlobalScopes()->firstOrFail()->total)->toBe(200.0);
});

it('cannot order another store product', function () {
    $store = openStore();

    $other = Store::factory()->create();
    $foreign = asStore($other, fn () => Product::factory()->active()->create(['track_stock' => false]));

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
    ])->assertNotFound();

    expect(Order::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects an empty basket', function () {
    $store = openStore();

    $this->post(route('public.orders.place', $store->slug), ['items' => []])
        ->assertSessionHasErrors('items');
});

it('throttles a flood of orders', function () {
    $store = openStore();
    $product = Product::factory()->active()->create(['track_stock' => false]);

    $payload = ['items' => [['product_id' => $product->id, 'quantity' => 1]]];

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('public.orders.place', $store->slug), $payload);
    }

    $this->post(route('public.orders.place', $store->slug), $payload)
        ->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| The status page
|--------------------------------------------------------------------------
*/

it('shows an order by its token', function () {
    $store = openStore();
    $order = Order::factory()->create(['store_id' => $store->id, 'customer_name' => 'Joy']);

    $this->get(route('public.status', $order->token))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Public/Status')
            ->where('order.queue_number', $order->queue_number)
            ->where('order.status', 'placed')
            ->where('order.customer_name', 'Joy')
        );
});

it('returns nothing for a token that does not exist', function () {
    $this->get(route('public.status', str_repeat('a', 40)))->assertNotFound();
});

it('cannot reach an order by its id', function () {
    $store = openStore();
    $order = Order::factory()->create(['store_id' => $store->id]);

    $this->get('/o/'.$order->id)->assertNotFound();
});

it('shows the rejection reason to the customer', function () {
    $store = openStore();
    $order = Order::factory()->rejected()->create([
        'store_id' => $store->id,
        'reject_reason' => 'Sold out of oat milk',
    ]);

    $this->get(route('public.status', $order->token))
        ->assertInertia(fn ($page) => $page
            ->where('order.status', 'rejected')
            ->where('order.reject_reason', 'Sold out of oat milk')
        );
});

it('keeps an existing order readable after the store turns ordering off', function () {
    $store = openStore();
    $order = Order::factory()->create(['store_id' => $store->id]);

    $store->update(['online_ordering_enabled' => false]);

    $this->get(route('public.status', $order->token))->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| It reaches the counter
|--------------------------------------------------------------------------
*/

it('puts a customer order on the staff queue', function () {
    $store = openStore();
    $product = Product::factory()->active()->create(['track_stock' => false]);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
        'customer_name' => 'Ana',
    ]);

    $staff = User::factory()->cashier()->create(['store_id' => $store->id]);

    $this->actingAs($staff)
        ->get(route('orders.index'))
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});
