<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidModifierException;
use App\Exceptions\ProductInactiveException;
use App\Exceptions\ProductUnavailableException;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Turns a posted line — a product id, a size, some add-ons — into what may
 * actually be charged for it.
 *
 * This is the only place a price is decided, so a cashier's checkout and a
 * customer's online order cannot disagree about what a 16oz oat latte costs.
 * Nothing here trusts a price from the caller; every peso is read from the
 * store's own menu.
 */
class MenuPricer
{
    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     product: Product,
     *     variant: ProductVariant|null,
     *     modifiers: array<int, array{id: int, name: string, price_delta: float}>,
     *     quantity: int,
     *     unit_price: float,
     *     cost_price: float,
     *     line_total: float
     * }
     */
    public function resolve(array $item, bool $lockProduct = false): array
    {
        $query = Product::query();

        if ($lockProduct) {
            $query->lockForUpdate();
        }

        $product = $query->findOrFail($item['product_id']);

        if (! $product->is_active) {
            throw new ProductInactiveException($product);
        }

        if (! $product->is_available) {
            throw new ProductUnavailableException($product);
        }

        $quantity = (int) $item['quantity'];

        if ($product->track_stock && $product->stock_quantity < $quantity) {
            throw new InsufficientStockException($product, $quantity);
        }

        $variant = $this->resolveVariant($product, $item['variant_id'] ?? null);
        $modifiers = $this->resolveModifiers($product, $item['modifier_ids'] ?? []);

        $basePrice = $variant !== null ? $variant->selling_price : $product->selling_price;
        $baseCost = $variant !== null && $variant->cost_price !== null
            ? $variant->cost_price
            : $product->cost_price;

        $unitPrice = (float) $basePrice + array_sum(array_column($modifiers, 'price_delta'));

        return [
            'product' => $product,
            'variant' => $variant,
            'modifiers' => $modifiers,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => (float) $baseCost,
            'line_total' => $unitPrice * $quantity,
        ];
    }

    /**
     * The variant must belong to this product — and, through the store scope,
     * to this store.
     */
    private function resolveVariant(Product $product, mixed $variantId): ?ProductVariant
    {
        if ($variantId === null) {
            return null;
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->active()
            ->find($variantId);

        if ($variant === null) {
            throw InvalidModifierException::notOnProduct($product->name);
        }

        return $variant;
    }

    /**
     * Validate the add-on selection against the groups actually attached to the
     * product, then snapshot the chosen options with the price charged for them.
     *
     * @param  array<int, mixed>  $modifierIds
     * @return array<int, array{id: int, name: string, price_delta: float}>
     */
    private function resolveModifiers(Product $product, array $modifierIds): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ModifierGroup> $groups */
        $groups = $product->modifierGroups()->with('modifiers')->get();

        $selected = $modifierIds === []
            ? collect()
            : Modifier::query()->active()->whereIn('id', $modifierIds)->get();

        if ($selected->count() !== count(array_unique($modifierIds))) {
            throw InvalidModifierException::notOnProduct($product->name);
        }

        $allowed = $groups->pluck('id');

        foreach ($selected as $modifier) {
            if (! $allowed->contains($modifier->modifier_group_id)) {
                throw InvalidModifierException::notOnProduct($product->name);
            }
        }

        foreach ($groups as $group) {
            $chosen = $selected->where('modifier_group_id', $group->id)->count();

            if ($chosen > $group->max_select) {
                throw InvalidModifierException::tooMany($group->name, $group->max_select);
            }

            if ($chosen < $group->min_select) {
                throw InvalidModifierException::required($group->name, $group->min_select);
            }
        }

        return $selected
            ->sortBy('sort_order')
            ->map(fn (Modifier $modifier) => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price_delta' => (float) $modifier->price_delta,
            ])
            ->values()
            ->all();
    }
}
