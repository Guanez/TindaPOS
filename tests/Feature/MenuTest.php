<?php

declare(strict_types=1);

use App\Exceptions\InvalidModifierException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\SaleService;

/**
 * @param  array<string, mixed>  $line
 */
function checkoutLine(User $cashier, array $line): App\Models\Sale
{
    return resolve(SaleService::class)->checkout([
        'items' => [$line],
        'payment_method' => 'cash',
    ], $cashier);
}

/*
|--------------------------------------------------------------------------
| Variants
|--------------------------------------------------------------------------
*/

it('charges the product price when no variant is chosen', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 50]);

    $sale = checkoutLine($cashier, ['product_id' => $product->id, 'quantity' => 2]);

    expect((float) $sale->total)->toBe(100.0)
        ->and($sale->items->first()->variant_name)->toBeNull();
});

it('charges the variant price instead of the product price', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 120]);
    $large = ProductVariant::factory()->for($product)->priced(150, 40)->create(['name' => '16oz']);

    $sale = checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 2,
        'variant_id' => $large->id,
    ]);

    $item = $sale->items->first();

    expect((float) $sale->total)->toBe(300.0)
        ->and($item->variant_name)->toBe('16oz')
        ->and((float) $item->cost_price)->toBe(40.0);
});

it('rejects a variant belonging to a different product', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();
    $foreign = ProductVariant::factory()->create(['name' => '16oz']);

    expect(fn () => checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 1,
        'variant_id' => $foreign->id,
    ]))->toThrow(InvalidModifierException::class);
});

it('rejects a variant belonging to another store', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    $other = Store::factory()->create();
    $foreign = asStore($other, fn () => ProductVariant::factory()->create());

    expect(fn () => checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 1,
        'variant_id' => $foreign->id,
    ]))->toThrow(InvalidModifierException::class);
});

/*
|--------------------------------------------------------------------------
| Modifiers
|--------------------------------------------------------------------------
*/

it('adds modifier price deltas to the unit price', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 120]);

    $group = ModifierGroup::factory()->multiSelect(3)->create(['name' => 'Extras']);
    $product->modifierGroups()->attach($group);

    $oat = Modifier::factory()->for($group, 'group')->costing(30)->create(['name' => 'Oat milk']);
    $shot = Modifier::factory()->for($group, 'group')->costing(25)->create(['name' => 'Extra shot']);

    $sale = checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 2,
        'modifier_ids' => [$oat->id, $shot->id],
    ]);

    $item = $sale->items->first();

    // (120 + 30 + 25) x 2
    expect((float) $sale->total)->toBe(350.0)
        ->and((float) $item->selling_price)->toBe(175.0)
        ->and($item->modifiers)->toHaveCount(2)
        ->and($item->describe())->toBe($product->name.' + Oat milk, Extra shot');
});

it('stacks modifiers on top of a variant price', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['selling_price' => 120]);
    $large = ProductVariant::factory()->for($product)->priced(150)->create(['name' => '16oz']);

    $group = ModifierGroup::factory()->create(['name' => 'Milk']);
    $product->modifierGroups()->attach($group);
    $oat = Modifier::factory()->for($group, 'group')->costing(30)->create(['name' => 'Oat milk']);

    $sale = checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 1,
        'variant_id' => $large->id,
        'modifier_ids' => [$oat->id],
    ]);

    expect((float) $sale->total)->toBe(180.0)
        ->and($sale->items->first()->describe())->toBe($product->name.' (16oz) + Oat milk');
});

it('rejects a modifier from a group not attached to the product', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    $unattached = ModifierGroup::factory()->create();
    $modifier = Modifier::factory()->for($unattached, 'group')->create();

    expect(fn () => checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 1,
        'modifier_ids' => [$modifier->id],
    ]))->toThrow(InvalidModifierException::class);
});

it('rejects more selections than the group allows', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    $group = ModifierGroup::factory()->create(['name' => 'Milk', 'max_select' => 1]);
    $product->modifierGroups()->attach($group);

    $oat = Modifier::factory()->for($group, 'group')->create(['name' => 'Oat']);
    $soy = Modifier::factory()->for($group, 'group')->create(['name' => 'Soy']);

    expect(fn () => checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 1,
        'modifier_ids' => [$oat->id, $soy->id],
    ]))->toThrow(InvalidModifierException::class, 'Choose at most 1');
});

it('rejects an empty selection for a required group', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create();

    $group = ModifierGroup::factory()->required()->create(['name' => 'Milk']);
    $product->modifierGroups()->attach($group);
    Modifier::factory()->for($group, 'group')->create();

    expect(fn () => checkoutLine($cashier, [
        'product_id' => $product->id,
        'quantity' => 1,
    ]))->toThrow(InvalidModifierException::class, 'Choose at least 1');
});

/*
|--------------------------------------------------------------------------
| Stock tracking is now optional
|--------------------------------------------------------------------------
*/

it('does not move stock for a product that does not track it', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->create([
        'track_stock' => false,
        'stock_quantity' => 0,
    ]);

    $sale = checkoutLine($cashier, ['product_id' => $product->id, 'quantity' => 50]);

    expect($product->fresh()->stock_quantity)->toBe(0)
        ->and($sale->items)->toHaveCount(1)
        ->and($product->stockLogs()->count())->toBe(0);
});

it('still blocks an oversold product that does track stock', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(3)->create();

    expect(fn () => checkoutLine($cashier, ['product_id' => $product->id, 'quantity' => 4]))
        ->toThrow(App\Exceptions\InsufficientStockException::class);
});

it('does not restore stock on void for an untracked product', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->create(['track_stock' => false, 'stock_quantity' => 0]);

    $sale = checkoutLine($cashier, ['product_id' => $product->id, 'quantity' => 5]);
    resolve(SaleService::class)->voidSale($sale, $cashier, 'Wrong order');

    expect($product->fresh()->stock_quantity)->toBe(0);
});

it('leaves untracked products out of the low stock list', function () {
    Product::factory()->active()->create([
        'track_stock' => false,
        'stock_quantity' => 0,
        'low_stock_threshold' => 10,
    ]);

    expect(Product::lowStock()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Availability
|--------------------------------------------------------------------------
*/

it('refuses to sell a product switched off mid-service', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['is_available' => false]);

    expect(fn () => checkoutLine($cashier, ['product_id' => $product->id, 'quantity' => 1]))
        ->toThrow(ProductUnavailableException::class, 'sold out right now');
});

it('surfaces an unavailable product as a checkout error rather than a 500', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->withStock(10)->create(['is_available' => false]);

    $this->actingAs($cashier)
        ->post(route('pos.checkout'), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])
        ->assertSessionHasErrors('checkout');
});

/*
|--------------------------------------------------------------------------
| Per-store uniqueness
|--------------------------------------------------------------------------
*/

it('lets two stores use the same sku', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => Product::factory()->create(['sku' => 'COFFEE-01']));

    $mine = Product::factory()->create(['sku' => 'COFFEE-01']);

    expect($mine->sku)->toBe('COFFEE-01');
});

it('lets two stores use the same category name', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => App\Models\Category::factory()->create(['name' => 'Beverages']));

    $mine = App\Models\Category::factory()->create(['name' => 'Beverages']);

    expect($mine->name)->toBe('Beverages');
});

it('still rejects a duplicate sku inside one store', function () {
    Product::factory()->create(['sku' => 'COFFEE-01']);
    $admin = User::factory()->admin()->create();
    $category = App\Models\Category::factory()->create();

    $this->actingAs($admin)
        ->post(route('inventory.store'), [
            'category_id' => $category->id,
            'name' => 'Another coffee',
            'sku' => 'COFFEE-01',
            'cost_price' => 10,
            'selling_price' => 20,
            'stock_quantity' => 5,
        ])
        ->assertSessionHasErrors('sku');
});
