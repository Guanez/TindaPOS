<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidStockException;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Restock a product — add stock quantity.
     */
    public function restock(int $productId, int $quantity, User $user, string $reason = 'Restock'): Product
    {
        return DB::transaction(function () use ($productId, $quantity, $user, $reason) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            if ($quantity <= 0) {
                throw new InvalidStockException('Restock quantity must be positive.');
            }

            $stockBefore = $product->stock_quantity;
            $product->increment('stock_quantity', $quantity);

            StockLog::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'restock',
                'quantity_change' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore + $quantity,
                'reason' => $reason,
            ]);

            return $product->fresh();
        });
    }

    /**
     * Adjust stock — can be positive or negative.
     */
    public function adjust(int $productId, int $quantity, User $user, string $reason = 'Manual adjustment'): Product
    {
        return DB::transaction(function () use ($productId, $quantity, $user, $reason) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            $newStock = $product->stock_quantity + $quantity;
            if ($newStock < 0) {
                throw new InvalidStockException(
                    "Adjustment would result in negative stock ({$newStock}). Current: {$product->stock_quantity}"
                );
            }

            $stockBefore = $product->stock_quantity;
            $product->update(['stock_quantity' => $newStock]);

            StockLog::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'adjustment',
                'quantity_change' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'reason' => $reason,
            ]);

            return $product->fresh();
        });
    }
}
