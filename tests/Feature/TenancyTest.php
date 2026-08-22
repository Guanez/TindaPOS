<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLog;
use App\Models\Store;
use App\Models\User;
use App\Services\ReportService;
use App\Support\StoreContext;

/*
|--------------------------------------------------------------------------
| Isolation — a query as one store cannot see another store's rows
|--------------------------------------------------------------------------
| With several cafes in one database and a public ordering surface, a missed
| scope stops being a bug and becomes a breach. Each tenant model is asserted
| individually rather than trusting the trait to have been applied.
*/

it('hides another store products', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Product::factory()->count(3)->create());
    Product::factory()->count(2)->create();

    expect(Product::query()->count())->toBe(2)
        ->and(asStore($other, fn () => Product::query()->count()))->toBe(3);
});

it('hides another store categories', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Category::factory()->count(4)->create());
    Category::factory()->count(1)->create();

    expect(Category::query()->count())->toBe(1);
});

it('hides another store sales', function () {
    $other = Store::factory()->create();
    asStore($other, function () {
        $user = User::factory()->cashier()->create();
        Sale::factory()->for($user)->count(3)->create();
    });

    expect(Sale::query()->count())->toBe(0);
});

it('hides another store stock logs', function () {
    $other = Store::factory()->create();
    asStore($other, function () {
        StockLog::factory()->count(2)->create();
    });

    expect(StockLog::query()->count())->toBe(0);
});

it('hides another store users', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => User::factory()->count(3)->create());
    User::factory()->count(1)->create();

    expect(User::query()->count())->toBe(1);
});

it('cannot fetch another store record by id', function () {
    $other = Store::factory()->create();
    $foreign = asStore($other, fn () => Product::factory()->create());

    expect(Product::query()->find($foreign->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Assignment — new records join the current store automatically
|--------------------------------------------------------------------------
*/

it('stamps the current store onto new records', function () {
    $product = Product::factory()->create();

    expect($product->store_id)->toBe(currentStore()->id);
});

it('keeps an explicitly set store', function () {
    $other = Store::factory()->create();
    $product = Product::factory()->create(['store_id' => $other->id]);

    expect($product->store_id)->toBe($other->id);
});

/*
|--------------------------------------------------------------------------
| Reporting — aggregates must not pool takings across stores
|--------------------------------------------------------------------------
| sale_items carries no store_id of its own; it is reached through the sale,
| so this proves the scope survives a whereHas subquery.
*/

it('keeps report totals inside one store', function () {
    $other = Store::factory()->create();

    asStore($other, function () {
        $user = User::factory()->cashier()->create();
        $sale = Sale::factory()->completed()->for($user)->create([
            'total' => 5000,
            'created_at' => today()->setTime(10, 0),
        ]);
        SaleItem::factory()->for($sale)->create([
            'cost_price' => 10, 'selling_price' => 100, 'quantity' => 50, 'line_total' => 5000,
        ]);
    });

    $user = User::factory()->cashier()->create();
    $sale = Sale::factory()->completed()->for($user)->create([
        'total' => 100,
        'created_at' => today()->setTime(10, 0),
    ]);
    SaleItem::factory()->for($sale)->create([
        'cost_price' => 10, 'selling_price' => 100, 'quantity' => 1, 'line_total' => 100,
    ]);

    $report = resolve(ReportService::class)->daily(today()->toDateString());

    expect($report['revenue'])->toBe(100.0)
        ->and($report['transactions'])->toBe(1)
        ->and($report['profit'])->toBe(90.0);
});

/*
|--------------------------------------------------------------------------
| Requests — the signed-in user pins the store for the whole request
|--------------------------------------------------------------------------
*/

it('scopes a page to the store of the signed-in user', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Product::factory()->active()->count(5)->create());

    $mine = Product::factory()->active()->count(2)->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page->has('products.data', 2));
});

it('leaves queries unscoped when no store is in context', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Product::factory()->count(3)->create());
    Product::factory()->count(2)->create();

    app(StoreContext::class)->forget();

    // A platform owner, or an artisan command, sees across stores by design.
    expect(Product::query()->count())->toBe(5);
});
