<?php

declare(strict_types=1);

use App\Support\StoreVocabulary;

/*
|--------------------------------------------------------------------------
| Store vocabulary
|--------------------------------------------------------------------------
| The point of the lexicon is that words live in exactly one place. These
| tests pin the words themselves, so a rename is a deliberate act rather than
| something that drifts.
*/

it('calls it a menu in a cafe', function () {
    $words = StoreVocabulary::for('cafe');

    expect($words['catalogue'])->toBe('Menu')
        ->and($words['item'])->toBe('Item')
        ->and($words['items'])->toBe('Items');
});

it('calls it inventory in a sari-sari store', function () {
    $words = StoreVocabulary::for('sari_sari');

    expect($words['catalogue'])->toBe('Inventory')
        ->and($words['item'])->toBe('Product');
});

it('falls back to retail wording when there is no store', function () {
    // A platform admin, or an artisan command: no shop, so no shop's words.
    expect(StoreVocabulary::for(null))->toBe(StoreVocabulary::for('sari_sari'));
});

it('falls back rather than breaking on a store type it does not know', function () {
    expect(StoreVocabulary::for('bakery'))->toBe(StoreVocabulary::for('sari_sari'));
});

it('defines the same terms for every store type', function () {
    $expected = array_keys(StoreVocabulary::for(StoreVocabulary::DEFAULT_TYPE));

    foreach (StoreVocabulary::knownTypes() as $type) {
        expect(array_keys(StoreVocabulary::for($type)))->toBe($expected, "{$type} is missing a term");
    }
});

it('covers every store type the database allows', function () {
    // enum('sari_sari','cafe') on stores.type — a new type added to the schema
    // without a lexicon entry would silently read as a sari-sari store.
    expect(StoreVocabulary::knownTypes())->toBe(['sari_sari', 'cafe']);
});
