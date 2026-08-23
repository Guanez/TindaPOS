<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Store;
use App\Services\StoreStarterKit;
use App\Support\StoreContext;

function kit(): StoreStarterKit
{
    return resolve(StoreStarterKit::class);
}

/*
|--------------------------------------------------------------------------
| What a new shop starts with
|--------------------------------------------------------------------------
*/

it('gives a new cafe the add-on groups every cafe needs', function () {
    $cafe = Store::factory()->cafe()->create();

    kit()->applyTo($cafe);

    asStore($cafe, function () {
        $groups = ModifierGroup::query()->pluck('name');

        expect($groups)->toContain('Temperature', 'Milk', 'Extras');

        $temperature = ModifierGroup::query()->where('name', 'Temperature')->firstOrFail();

        // A drink has to be hot or iced; it cannot be neither.
        expect($temperature->min_select)->toBe(1)
            ->and($temperature->max_select)->toBe(1)
            ->and($temperature->modifiers->pluck('name'))->toContain('Hot', 'Iced');

        $milk = ModifierGroup::query()->where('name', 'Milk')->firstOrFail();
        $oat = $milk->modifiers->firstWhere('name', 'Oat milk');

        expect((float) $oat->price_delta)->toBe(30.0);
    });
});

it('gives a new cafe its categories', function () {
    $cafe = Store::factory()->cafe()->create();

    kit()->applyTo($cafe);

    asStore($cafe, fn () => expect(Category::query()->pluck('name'))
        ->toContain('Espresso', 'Non-Coffee', 'Pastries'));
});

it('gives a sari-sari store retail categories and no add-ons', function () {
    $shop = Store::factory()->create(['type' => 'sari_sari']);

    kit()->applyTo($shop);

    asStore($shop, function () {
        expect(Category::query()->pluck('name'))->toContain('Beverages', 'Snacks', 'Household')
            // Sachets and tins have no options to choose.
            ->and(ModifierGroup::query()->count())->toBe(0);
    });
});

it('never invents products', function () {
    // Structure is predictable from the type; prices and menu items are the
    // shop's own identity and guessing them creates cleanup, not a head start.
    $cafe = Store::factory()->cafe()->create();

    kit()->applyTo($cafe);

    asStore($cafe, fn () => expect(Product::query()->count())->toBe(0));
});

/*
|--------------------------------------------------------------------------
| Safety
|--------------------------------------------------------------------------
*/

it('changes nothing when applied twice', function () {
    $cafe = Store::factory()->cafe()->create();

    kit()->applyTo($cafe);

    $categories = asStore($cafe, fn () => Category::query()->count());
    $groups = asStore($cafe, fn () => ModifierGroup::query()->count());
    $modifiers = asStore($cafe, fn () => ModifierGroup::query()->with('modifiers')->get()->sum(fn ($g) => $g->modifiers->count()));

    kit()->applyTo($cafe);

    asStore($cafe, function () use ($categories, $groups, $modifiers) {
        expect(Category::query()->count())->toBe($categories)
            ->and(ModifierGroup::query()->count())->toBe($groups)
            ->and(ModifierGroup::query()->with('modifiers')->get()->sum(fn ($g) => $g->modifiers->count()))->toBe($modifiers);
    });
});

it('puts everything in the new shop and nowhere else', function () {
    $mine = currentStore();
    $newStore = Store::factory()->cafe()->create();

    $before = Category::query()->count();

    kit()->applyTo($newStore);

    // The creator's own shop is untouched — including a platform admin, who
    // has no store at all and must not have starter data land on them.
    expect(Category::query()->count())->toBe($before)
        ->and($mine->id)->not->toBe($newStore->id);

    asStore($newStore, fn () => expect(Category::query()->count())->toBeGreaterThan(0));
});

it('works when no store is in context at all', function () {
    // How it will actually be called: a platform admin creating a shop.
    app(StoreContext::class)->forget();

    $cafe = Store::factory()->cafe()->create();

    kit()->applyTo($cafe);

    asStore($cafe, fn () => expect(ModifierGroup::query()->count())->toBe(3));

    // And it leaves the context as it found it.
    expect(app(StoreContext::class)->id())->toBeNull();
});

it('has no categories to offer a store type it does not know', function () {
    // Not testable through a Store: stores.type is an enum, and the database
    // refuses 'bakery' outright — a stronger guarantee than this fallback.
    // The fallback still matters, because a type can be added to the schema
    // before anyone writes its kit, and that should be an empty shop rather
    // than a fatal error.
    expect(StoreStarterKit::categoriesFor('bakery'))->toBe([])
        ->and(StoreStarterKit::categoriesFor('cafe'))->not->toBe([]);
});
