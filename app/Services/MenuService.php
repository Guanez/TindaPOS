<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Editing the shape of a cafe's menu: the sizes a product comes in and the
 * add-on groups offered with it.
 *
 * Every lookup here goes through a store-scoped query, so a payload naming
 * another store's group or variant simply finds nothing.
 */
class MenuService
{
    /**
     * Replace a product's sizes with the given set.
     *
     * Variants absent from the payload are removed. Sale history is unaffected:
     * sale items snapshot the variant name and release the id on delete.
     *
     * @param  array<int, array<string, mixed>>  $variants
     */
    public function syncVariants(Product $product, array $variants): void
    {
        DB::transaction(function () use ($product, $variants) {
            $kept = [];

            foreach ($variants as $index => $payload) {
                $attributes = [
                    'name' => $payload['name'],
                    'cost_price' => $payload['cost_price'] ?? null,
                    'selling_price' => $payload['selling_price'],
                    'is_default' => (bool) ($payload['is_default'] ?? false),
                    'sort_order' => $index,
                    'is_active' => (bool) ($payload['is_active'] ?? true),
                ];

                /** @var ProductVariant|null $existing */
                $existing = isset($payload['id'])
                    ? $product->variants()->find($payload['id'])
                    : null;

                if ($existing !== null) {
                    $existing->update($attributes);
                    $kept[] = $existing->id;

                    continue;
                }

                /** @var ProductVariant $created */
                $created = $product->variants()->create($attributes);
                $kept[] = $created->id;
            }

            $product->variants()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
        });
    }

    /**
     * Attach exactly the given add-on groups to a product.
     *
     * @param  array<int, int>  $groupIds
     */
    public function syncModifierGroups(Product $product, array $groupIds): void
    {
        // Scoped: ids belonging to another store resolve to nothing.
        $valid = ModifierGroup::query()->whereIn('id', $groupIds)->pluck('id')->all();

        $product->modifierGroups()->sync($valid);
    }

    /**
     * Create an add-on group together with its options.
     *
     * @param  array<string, mixed>  $data
     */
    public function createGroup(array $data): ModifierGroup
    {
        return DB::transaction(function () use ($data) {
            $group = ModifierGroup::create([
                'name' => $data['name'],
                'min_select' => $data['min_select'] ?? 0,
                'max_select' => $data['max_select'] ?? 1,
            ]);

            $this->syncModifiers($group, $data['modifiers'] ?? []);

            return $group->load('modifiers');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGroup(ModifierGroup $group, array $data): ModifierGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update([
                'name' => $data['name'],
                'min_select' => $data['min_select'] ?? 0,
                'max_select' => $data['max_select'] ?? 1,
            ]);

            if (array_key_exists('modifiers', $data)) {
                $this->syncModifiers($group, $data['modifiers']);
            }

            return $group->load('modifiers');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $modifiers
     */
    private function syncModifiers(ModifierGroup $group, array $modifiers): void
    {
        $kept = [];

        foreach ($modifiers as $index => $payload) {
            $attributes = [
                'name' => $payload['name'],
                'price_delta' => $payload['price_delta'] ?? 0,
                'sort_order' => $index,
                'is_active' => (bool) ($payload['is_active'] ?? true),
            ];

            /** @var Modifier|null $existing */
            $existing = isset($payload['id'])
                ? $group->modifiers()->find($payload['id'])
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $kept[] = $existing->id;

                continue;
            }

            /** @var Modifier $created */
            $created = $group->modifiers()->create($attributes);
            $kept[] = $created->id;
        }

        $group->modifiers()->whereNotIn('id', $kept === [] ? [0] : $kept)->delete();
    }
}
