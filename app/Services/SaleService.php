<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\ProductInactiveException;
use App\Exceptions\SaleAlreadyVoidedException;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaleService
{
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
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if (! $product->is_active) {
                    throw new ProductInactiveException($product);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw new InsufficientStockException($product, $item['quantity']);
                }

                $lineTotal = $product->selling_price * $item['quantity'];
                $subtotal += $lineTotal;
                $itemCount += $item['quantity'];

                $resolvedItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'line_total' => $lineTotal,
                ];
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
                    'product_name' => $product->name,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'quantity' => $ri['quantity'],
                    'line_total' => $ri['line_total'],
                ]);

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

                if ($product) {
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
