<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Support\Facades\DB;

/**
 * What a new shop starts with, based on the kind of shop it is.
 *
 * A store created from the platform console arrives completely empty, so the
 * owner's first act is building "Temperature / Milk / Extras" by hand — work
 * we already know the answer to for every cafe.
 *
 * Deliberately structure only: categories and add-on groups, never products.
 * Prices and menu items are the shop's own identity, and guessing them would
 * hand the owner a cleanup job instead of a head start. The line is "things
 * every shop of this type has" versus "things this shop sells".
 *
 * Idempotent throughout — applying it twice changes nothing, so it is safe to
 * run against a store that has already been set up.
 */
class StoreStarterKit
{
    /** @var array<string, list<string>> */
    private const CATEGORIES = [
        'cafe' => ['Espresso', 'Non-Coffee', 'Pastries'],
        'sari_sari' => ['Beverages', 'Snacks', 'Canned Goods', 'Noodles', 'Household', 'Personal Care'],
    ];

    /**
     * Add-on groups a Philippine cafe almost always needs. A sari-sari store
     * sells sachets and tins, which have no options, so it gets none.
     *
     * @var array<string, array{min: int, max: int, options: list<array{0: string, 1: int}>}>
     */
    private const CAFE_MODIFIER_GROUPS = [
        'Temperature' => [
            'min' => 1,
            'max' => 1,
            'options' => [['Hot', 0], ['Iced', 10]],
        ],
        'Milk' => [
            'min' => 0,
            'max' => 1,
            'options' => [['Fresh milk', 0], ['Oat milk', 30], ['Soy milk', 25], ['Almond milk', 35]],
        ],
        'Extras' => [
            'min' => 0,
            'max' => 3,
            'options' => [['Extra shot', 25], ['Vanilla syrup', 20], ['Caramel syrup', 20], ['Whipped cream', 15]],
        ],
    ];

    public function __construct(
        private readonly StoreContext $context
    ) {}

    /**
     * The starter content belongs to the new shop, not to whoever created it —
     * a platform admin has no store of their own, so the context is named
     * explicitly rather than inherited.
     */
    public function applyTo(Store $store): void
    {
        $this->context->runFor($store->id, function () use ($store): void {
            DB::transaction(function () use ($store): void {
                $this->seedCategories($store->type);

                if ($store->type === 'cafe') {
                    $this->seedCafeModifierGroups();
                }
            });
        });
    }

    private function seedCategories(string $type): void
    {
        foreach (self::CATEGORIES[$type] ?? [] as $name) {
            Category::query()->firstOrCreate(['name' => $name]);
        }
    }

    private function seedCafeModifierGroups(): void
    {
        foreach (self::CAFE_MODIFIER_GROUPS as $name => $definition) {
            $group = ModifierGroup::query()->firstOrCreate(
                ['name' => $name],
                ['min_select' => $definition['min'], 'max_select' => $definition['max']]
            );

            foreach ($definition['options'] as $index => $option) {
                $group->modifiers()->firstOrCreate(
                    ['name' => $option[0]],
                    ['price_delta' => $option[1], 'sort_order' => $index]
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function categoriesFor(string $type): array
    {
        return self::CATEGORIES[$type] ?? [];
    }
}
