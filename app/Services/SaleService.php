<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidModifierException;
use App\Exceptions\ProductInactiveException;
use App\Exceptions\ProductUnavailableException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaleService
{
    /**
     * How many times a checkout is retried when two terminals happen to
     * claim the same receipt number.
     */
    private const RECEIPT_COLLISION_RETRIES = 3;

    /**
     * Process a checkout — the most critical business operation.
     * Uses a database transaction to ensure atomicity:
     * 1. Validate all items have sufficient stock
     * 2. Create the sale record
     * 3. Create sale items (snapshot product info)
     * 4. Deduct stock from each product
     * 5. Log all stock changes
     *
     * @param  array<string, mixed>  $data
     */
    public function checkout(array $data, User $cashier): Sale
    {
        for ($attempt = 1; $attempt <= self::RECEIPT_COLLISION_RETRIES; $attempt++) {
            try {
                return $this->attemptCheckout($data, $cashier);
            } catch (UniqueConstraintViolationException $e) {
                // The attempt rolled back, so the retry re-reads the counter.
                if ($attempt === self::RECEIPT_COLLISION_RETRIES) {
                    throw $e;
                }
            }
        }

        throw new LogicException('Unreachable: the loop above always returns or throws.');
    }

    /**
     * A single checkout attempt — atomic from stock check to stock log.
     *
     * @param  array<string, mixed>  $data
     */
    private function attemptCheckout(array $data, User $cashier): Sale
    {
        return DB::transaction(function () use ($data, $cashier) {
            $items = $data['items'];
            $discount = $data['discount'] ?? 0;
            $paymentMethod = $data['payment_method'] ?? 'cash';
            $cashReceived = $data['cash_received'] ?? null;

            // 1. Validate stock & calculate totals
            $subtotal = 0;
            $itemCount = 0;
            $resolvedItems = [];

            foreach ($items as $item) {
                $resolved = $this->resolveLine($item);

                $subtotal += $resolved['line_total'];
                $itemCount += $resolved['quantity'];

                $resolvedItems[] = $resolved;
            }

            // 2. Calculate final total
            $total = max(0, $subtotal - $discount);
            $changeAmount = ($paymentMethod === 'cash' && $cashReceived)
                ? max(0, $cashReceived - $total)
                : null;

            // 3. Create sale
            $sale = Sale::create([
                'receipt_number' => Sale::generateReceiptNumber(),
                'user_id' => $cashier->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'item_count' => $itemCount,
                'payment_method' => $paymentMethod,
                'cash_received' => $cashReceived,
                'change_amount' => $changeAmount,
                'status' => 'completed',
            ]);

            // 4. Create sale items + deduct stock + log
            foreach ($resolvedItems as $ri) {
                $product = $ri['product'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $ri['variant']?->id,
                    'product_name' => $product->name,
                    'variant_name' => $ri['variant']?->name,
                    'modifiers' => $ri['modifiers'] === [] ? null : $ri['modifiers'],
                    'cost_price' => $ri['cost_price'],
                    'selling_price' => $ri['unit_price'],
                    'quantity' => $ri['quantity'],
                    'line_total' => $ri['line_total'],
                ]);

                // A cafe does not count lattes, so only stocked goods move.
                if (! $product->track_stock) {
                    continue;
                }

                $stockBefore = $product->stock_quantity;
                $product->decrement('stock_quantity', $ri['quantity']);

                StockLog::create([
                    'product_id' => $product->id,
                    'user_id' => $cashier->id,
                    'sale_id' => $sale->id,
                    'type' => 'sale',
                    'quantity_change' => -$ri['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockBefore - $ri['quantity'],
                    'reason' => "Sale #{$sale->receipt_number}",
                ]);
            }

            return $sale->load('items', 'user');
        });
    }

    /**
     * Turn one posted line into what will actually be charged and recorded.
     *
     * A line with no variant and no modifiers resolves to exactly the product's
     * own price, which is why every pre-menu sale behaves identically.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resolveLine(array $item): array
    {
        $product = Product::lockForUpdate()->findOrFail($item['product_id']);

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
        $costPrice = (float) $baseCost;

        return [
            'product' => $product,
            'variant' => $variant,
            'modifiers' => $modifiers,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'cost_price' => $costPrice,
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

    /**
     * Void a sale — restore stock and mark as voided.
     */
    public function voidSale(Sale $sale, User $user, string $reason): Sale
    {
        if ($sale->isVoided()) {
            throw new SaleAlreadyVoidedException($sale);
        }

        return DB::transaction(function () use ($sale, $user, $reason) {
            // Restore stock for each item
            /** @var SaleItem $item */
            foreach ($sale->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product && $product->track_stock) {
                    $stockBefore = $product->stock_quantity;
                    $product->increment('stock_quantity', $item->quantity);

                    StockLog::create([
                        'product_id' => $product->id,
                        'user_id' => $user->id,
                        'sale_id' => $sale->id,
                        'type' => 'void_return',
                        'quantity_change' => $item->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore + $item->quantity,
                        'reason' => "Void sale #{$sale->receipt_number}: {$reason}",
                    ]);
                }
            }

            // Mark sale as voided
            $sale->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_by' => $user->id,
                'voided_at' => now(),
            ]);

            return $sale->fresh(['items', 'user', 'voidedByUser']);
        });
    }
}
