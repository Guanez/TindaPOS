<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The words a shop uses for its own things.
 *
 * A cafe sells from a menu; a sari-sari store sells from inventory. Saying
 * "Inventory" to a barista is a small thing that makes the software feel like
 * it was built for someone else.
 *
 * This is the ONLY place those words differ. Templates read `words.catalogue`
 * and never ask what type of shop they are in, so adding a bakery later is one
 * entry here rather than an audit of every heading in the application.
 *
 * Resist growing this. Most labels genuinely serve both worlds — Sales
 * History, Reports, Dashboard, Stock Logs all read correctly either way, and
 * "Sale" deliberately stays "Sale" because Order already means something else
 * in this system. Only add a term when the wrong word would actually read as
 * wrong to a shopkeeper.
 *
 * @phpstan-type Lexicon array{catalogue: string, item: string, items: string}
 */
class StoreVocabulary
{
    /**
     * Used when there is no store in context — a platform admin, an artisan
     * command. Retail wording is the safe neutral: it was the application's
     * only vocabulary before cafes existed.
     */
    public const DEFAULT_TYPE = 'sari_sari';

    /** @var array<string, Lexicon> */
    private const WORDS = [
        'sari_sari' => [
            'catalogue' => 'Inventory',
            'item' => 'Product',
            'items' => 'Products',
        ],
        'cafe' => [
            'catalogue' => 'Menu',
            'item' => 'Item',
            'items' => 'Items',
        ],
    ];

    /**
     * @return Lexicon
     */
    public static function for(?string $type): array
    {
        return self::WORDS[$type] ?? self::WORDS[self::DEFAULT_TYPE];
    }

    /**
     * @return list<string>
     */
    public static function knownTypes(): array
    {
        return array_keys(self::WORDS);
    }
}
