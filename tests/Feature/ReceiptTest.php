<?php

declare(strict_types=1);

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Store branding reaches the receipt
|--------------------------------------------------------------------------
| The settings screen lets a shop set a name, address and footer. Those are
| only worth editing if they actually appear on the thing called a receipt.
*/

it('shares the current store with every authenticated page', function () {
    currentStore()->update([
        'name' => 'Kape Lokal',
        'address' => '12 Maginhawa St',
        'phone' => '0917-555-0123',
        'receipt_footer' => 'Salamat! Balik ka ulit.',
    ]);

    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page
            ->where('store.name', 'Kape Lokal')
            ->where('store.address', '12 Maginhawa St')
            ->where('store.phone', '0917-555-0123')
            ->where('store.receipt_footer', 'Salamat! Balik ka ulit.')
        );
});

it('never leaks another store branding', function () {
    currentStore()->update(['name' => 'Mine']);

    $other = Store::factory()->create(['name' => 'Not Mine']);
    asStore($other, fn () => null);

    $cashier = User::factory()->cashier()->create();

    $this->actingAs($cashier)
        ->get(route('pos.index'))
        ->assertInertia(fn ($page) => $page->where('store.name', 'Mine'));
});

it('flashes the sold lines so the receipt can list them', function () {
    $cashier = User::factory()->cashier()->create();
    $product = Product::factory()->active()->create([
        'track_stock' => false,
        'selling_price' => 130,
        'name' => 'Cafe Latte',
    ]);
    $large = ProductVariant::factory()->for($product)->priced(170)->create(['name' => '16oz']);

    $group = ModifierGroup::factory()->create(['name' => 'Milk']);
    $product->modifierGroups()->attach($group);
    $oat = Modifier::factory()->for($group, 'group')->costing(30)->create(['name' => 'Oat milk']);

    $this->actingAs($cashier)->post(route('pos.checkout'), [
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 2,
            'variant_id' => $large->id,
            'modifier_ids' => [$oat->id],
        ]],
        'payment_method' => 'cash',
        'cash_received' => 500,
    ]);

    $sale = session('sale');

    expect($sale)->not->toBeNull()
        ->and($sale['items'])->toHaveCount(1);

    $line = $sale['items'][0];

    expect($line['product_name'])->toBe('Cafe Latte')
        ->and($line['variant_name'])->toBe('16oz')
        ->and($line['modifiers'][0]['name'])->toBe('Oat milk')
        ->and((float) $line['line_total'])->toBe(400.0);
});

it('gives a platform owner no store branding', function () {
    // A null store means someone operating the platform rather than a shop.
    //
    // BelongsToStore stamps the current store whenever store_id is null on
    // create, and cannot tell "not given" from "given as null" — so the store
    // is cleared afterwards. In practice a platform owner is made from tinker
    // or a seeder with no store in context, where the hook fills null anyway.
    $platformOwner = User::factory()->owner()->create();
    $platformOwner->update(['store_id' => null]);

    $this->actingAs($platformOwner)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('store', null));
});
