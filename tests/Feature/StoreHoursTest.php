<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\OpeningHours;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| Opening hours, end to end
|--------------------------------------------------------------------------
*/

function openShop(?array $hours = null): Store
{
    return Store::factory()->create([
        'online_ordering_enabled' => true,
        'hours' => $hours,
    ]);
}

function shopHours(string $open, string $close): array
{
    return array_fill_keys(OpeningHours::DAYS, ['open' => $open, 'close' => $close]);
}

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('lets a customer order during opening hours', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 10:00', 'Asia/Manila'));

    $store = openShop(shopHours('07:00', '18:00'));
    $product = Product::factory()->create(['store_id' => $store->id]);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(Order::withoutGlobalScopes()->where('store_id', $store->id)->count())->toBe(1);
});

it('refuses an order placed after closing time', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 23:30', 'Asia/Manila'));

    $store = openShop(shopHours('07:00', '18:00'));
    $product = Product::factory()->create(['store_id' => $store->id]);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items');

    expect(Order::withoutGlobalScopes()->where('store_id', $store->id)->count())->toBe(0);
});

it('tells the customer when it will open rather than just refusing', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 05:00', 'Asia/Manila'));

    $store = openShop(shopHours('07:00', '18:00'));
    $product = Product::factory()->create(['store_id' => $store->id]);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasErrors(['items' => 'Opens today at 7:00 AM — nothing has been ordered.']);
});

it('keeps taking orders at any hour from a shop that set none', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 03:00', 'Asia/Manila'));

    $store = openShop(null);
    $product = Product::factory()->create(['store_id' => $store->id]);

    $this->post(route('public.orders.place', $store->slug), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(Order::withoutGlobalScopes()->where('store_id', $store->id)->count())->toBe(1);
});

it('still shows the menu when the shop is shut, and says so', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 23:30', 'Asia/Manila'));

    $store = openShop(shopHours('07:00', '18:00'));
    Product::factory()->create(['store_id' => $store->id]);

    $this->get(route('public.menu', $store->slug))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Public/Menu')
            ->where('store.is_open', false)
            ->where('store.next_opening', 'Opens tomorrow at 7:00 AM')
            ->has('products.data', 1)
        );
});

it('saves a week from the settings form', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('store.update'), [
            'name' => $owner->store->name,
            'currency_symbol' => 'P',
            'online_ordering_enabled' => true,
            'hours' => [
                'mon' => ['open' => '08:00', 'close' => '17:00'],
                'sun' => null,
            ],
        ])
        ->assertRedirect();

    $hours = $owner->store->fresh()->hours;

    expect($hours['mon'])->toBe(['open' => '08:00', 'close' => '17:00'])
        ->and($hours['sun'])->toBeNull()
        // A day the form never mentioned is closed, not missing.
        ->and($hours)->toHaveKey('tue')
        ->and($hours['tue'])->toBeNull();
});

it('stores no hours at all when every day is closed', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('store.update'), [
            'name' => $owner->store->name,
            'currency_symbol' => 'P',
            'online_ordering_enabled' => true,
            'hours' => array_fill_keys(OpeningHours::DAYS, null),
        ])
        ->assertRedirect();

    expect($owner->store->fresh()->hours)->toBeNull();
});

it('rejects a time the form should never have sent', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('store.update'), [
            'name' => $owner->store->name,
            'currency_symbol' => 'P',
            'online_ordering_enabled' => true,
            'hours' => ['mon' => ['open' => 'half seven', 'close' => '17:00']],
        ])
        ->assertSessionHasErrors('hours.mon.open');
});

it('drops a day that is not a day', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->put(route('store.update'), [
            'name' => $owner->store->name,
            'currency_symbol' => 'P',
            'online_ordering_enabled' => true,
            'hours' => [
                'mon' => ['open' => '08:00', 'close' => '17:00'],
                'funday' => ['open' => '00:00', 'close' => '23:59'],
            ],
        ])
        ->assertRedirect();

    expect($owner->store->fresh()->hours)->not->toHaveKey('funday');
});
