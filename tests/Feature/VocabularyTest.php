<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| The lexicon reaches the screen
|--------------------------------------------------------------------------
| StoreVocabularyTest pins the words themselves. These pin the seam: that the
| shared Inertia payload actually carries the right set for the shop the user
| is in. The lexicon and the sharing live in different files owned by
| different people, which is exactly the kind of join that breaks quietly.
*/

it('gives a cafe its own words', function () {
    currentStore()->update(['type' => 'cafe']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('words.catalogue', 'Menu')
            ->where('words.item', 'Item')
            ->where('words.items', 'Items')
        );
});

it('gives a sari-sari store retail words', function () {
    currentStore()->update(['type' => 'sari_sari']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('words.catalogue', 'Inventory')
            ->where('words.item', 'Product')
        );
});

it('follows the shop rather than the person', function () {
    // The vocabulary keys off the store in context, not the user — which is
    // what makes it correct while a platform admin is standing inside a cafe.
    currentStore()->update(['type' => 'cafe']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('inventory.index'))
        ->assertInertia(fn ($page) => $page->where('words.catalogue', 'Menu'));
});
