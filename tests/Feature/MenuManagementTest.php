<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Sizes, saved with the product
|--------------------------------------------------------------------------
*/

it('saves sizes alongside a new product', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)->post(route('inventory.store'), [
        'category_id' => $category->id,
        'name' => 'Cafe Latte',
        'sku' => 'LAT-1',
        'cost_price' => 34,
        'selling_price' => 130,
        'stock_quantity' => 0,
        'track_stock' => false,
        'variants' => [
            ['name' => '12oz', 'cost_price' => 34, 'selling_price' => 130],
            ['name' => '16oz', 'cost_price' => 42, 'selling_price' => 170],
        ],
    ])->assertRedirect();

    $product = Product::where('sku', 'LAT-1')->firstOrFail();

    expect($product->variants)->toHaveCount(2)
        ->and($product->track_stock)->toBeFalse()
        ->and((float) $product->variants[1]->selling_price)->toBe(170.0)
        ->and($product->variants[0]->sort_order)->toBe(0);
});

it('updates and removes sizes to match the payload', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();

    $keep = ProductVariant::factory()->for($product)->create(['name' => '12oz', 'selling_price' => 130]);
    ProductVariant::factory()->for($product)->create(['name' => '16oz', 'selling_price' => 170]);

    $this->actingAs($admin)->put(route('inventory.update', $product), [
        'name' => $product->name,
        'variants' => [
            ['id' => $keep->id, 'name' => '12oz', 'selling_price' => 140],
        ],
    ])->assertRedirect();

    $product->refresh()->load('variants');

    expect($product->variants)->toHaveCount(1)
        ->and((float) $product->variants[0]->selling_price)->toBe(140.0);
});

it('ignores a size id belonging to another product', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    $foreign = ProductVariant::factory()->create(['name' => 'Foreign']);

    $this->actingAs($admin)->put(route('inventory.update', $product), [
        'name' => $product->name,
        'variants' => [
            ['id' => $foreign->id, 'name' => 'Mine', 'selling_price' => 100],
        ],
    ])->assertRedirect();

    // Treated as a new size on this product; the other product keeps its own.
    expect($product->refresh()->variants)->toHaveCount(1)
        ->and($product->variants[0]->id)->not->toBe($foreign->id)
        ->and($foreign->refresh()->name)->toBe('Foreign');
});

/*
|--------------------------------------------------------------------------
| Add-on groups
|--------------------------------------------------------------------------
*/

it('creates an add-on group with its options', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('modifierGroups.store'), [
        'name' => 'Milk',
        'min_select' => 0,
        'max_select' => 1,
        'modifiers' => [
            ['name' => 'Fresh milk', 'price_delta' => 0],
            ['name' => 'Oat milk', 'price_delta' => 30],
        ],
    ])->assertRedirect();

    $group = ModifierGroup::where('name', 'Milk')->firstOrFail();

    expect($group->modifiers)->toHaveCount(2)
        ->and((float) $group->modifiers[1]->price_delta)->toBe(30.0)
        ->and($group->isRequired())->toBeFalse();
});

it('rejects a group whose maximum is below its minimum', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('modifierGroups.store'), [
        'name' => 'Milk',
        'min_select' => 3,
        'max_select' => 1,
        'modifiers' => [['name' => 'Fresh milk', 'price_delta' => 0]],
    ])->assertSessionHasErrors('max_select');
});

it('rejects a group with no options', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('modifierGroups.store'), [
        'name' => 'Milk',
        'max_select' => 1,
        'modifiers' => [],
    ])->assertSessionHasErrors('modifiers');
});

it('lets two stores each have a group of the same name', function () {
    $other = Store::factory()->create();
    asStore($other, fn () => ModifierGroup::factory()->create(['name' => 'Milk']));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('modifierGroups.store'), [
        'name' => 'Milk',
        'max_select' => 1,
        'modifiers' => [['name' => 'Fresh milk', 'price_delta' => 0]],
    ])->assertSessionHasNoErrors();
});

it('deletes a group and detaches it from products', function () {
    $admin = User::factory()->admin()->create();
    $group = ModifierGroup::factory()->create();
    $product = Product::factory()->create();
    $product->modifierGroups()->attach($group);

    $this->actingAs($admin)
        ->delete(route('modifierGroups.destroy', $group))
        ->assertRedirect();

    expect(ModifierGroup::find($group->id))->toBeNull()
        ->and($product->refresh()->modifierGroups)->toHaveCount(0);
});

it('denies a cashier access to add-on groups', function () {
    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)->post(route('modifierGroups.store'), [
        'name' => 'Milk',
        'max_select' => 1,
        'modifiers' => [['name' => 'Fresh milk', 'price_delta' => 0]],
    ])->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Attaching groups to a product
|--------------------------------------------------------------------------
*/

it('attaches add-on groups to a product', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();
    $milk = ModifierGroup::factory()->create(['name' => 'Milk']);
    $extras = ModifierGroup::factory()->create(['name' => 'Extras']);

    $this->actingAs($admin)->put(route('inventory.update', $product), [
        'name' => $product->name,
        'modifier_group_ids' => [$milk->id, $extras->id],
    ])->assertRedirect();

    expect($product->refresh()->modifierGroups)->toHaveCount(2);
});

it('refuses to attach a group from another store', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();

    $other = Store::factory()->create();
    $foreign = asStore($other, fn () => ModifierGroup::factory()->create());

    $this->actingAs($admin)->put(route('inventory.update', $product), [
        'name' => $product->name,
        'modifier_group_ids' => [$foreign->id],
    ])->assertRedirect();

    expect($product->refresh()->modifierGroups)->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| The POS receives what it needs to sell a menu
|--------------------------------------------------------------------------
*/

it('sends sizes and add-ons to the POS', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->create(['track_stock' => false]);
    ProductVariant::factory()->for($product)->create(['name' => '16oz']);

    $group = ModifierGroup::factory()->create(['name' => 'Milk']);
    $group->modifiers()->create(['name' => 'Oat milk', 'price_delta' => 30, 'store_id' => $group->store_id]);
    $product->modifierGroups()->attach($group);

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->has('products.data.0.variants', 1)
            ->has('products.data.0.modifier_groups', 1)
            ->where('products.data.0.modifier_groups.0.name', 'Milk')
            ->has('products.data.0.modifier_groups.0.modifiers', 1)
        );
});

it('hides cost price of sizes from a cashier', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->create();
    ProductVariant::factory()->for($product)->create(['name' => '16oz', 'cost_price' => 40]);

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->missing('products.data.0.variants.0.cost_price')
            ->has('products.data.0.variants.0.selling_price')
        );
});
