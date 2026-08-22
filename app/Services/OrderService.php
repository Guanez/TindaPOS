<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The life of a customer order, from placement to collection.
 *
 * The one rule worth stating: this service never moves stock or money itself.
 * Settling an order hands the work to SaleService::checkout(), so the code
 * that locks rows and writes stock logs stays the only code that does.
 */
class OrderService
{
    /**
     * How many times placing an order is retried when two customers happen to
     * claim the same queue number.
     */
    private const QUEUE_COLLISION_RETRIES = 3;

    public function __construct(
        private readonly MenuPricer $pricer,
        private readonly SaleService $saleService,
    ) {}

    /**
     * Place an order. Nothing is reserved and no stock moves — preparation
     * starts when the customer pays at the till.
     *
     * @param  array<string, mixed>  $data
     */
    public function place(Store $store, array $data): Order
    {
        for ($attempt = 1; $attempt <= self::QUEUE_COLLISION_RETRIES; $attempt++) {
            try {
                return $this->attemptPlace($store, $data);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === self::QUEUE_COLLISION_RETRIES) {
                    throw $e;
                }
            }
        }

        throw new LogicException('Unreachable: the loop above always returns or throws.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attemptPlace(Store $store, array $data): Order
    {
        return DB::transaction(function () use ($store, $data) {
            $lines = [];
            $subtotal = 0.0;
            $itemCount = 0;

            foreach ($data['items'] as $item) {
                $resolved = $this->pricer->resolve($item);

                $subtotal += $resolved['line_total'];
                $itemCount += $resolved['quantity'];
                $lines[] = $resolved;
            }

            $order = Order::create([
                'store_id' => $store->id,
                'queue_date' => today(),
                'queue_number' => Order::nextQueueNumber($store->id),
                'token' => Str::random(40),
                'customer_name' => $data['customer_name'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => OrderStatus::Placed,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'item_count' => $itemCount,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                /** @var OrderItem $orderItem */
                $orderItem = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id,
                    'product_name' => $line['product']->name,
                    'variant_name' => $line['variant']?->name,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);

                foreach ($line['modifiers'] as $modifier) {
                    $orderItem->modifiers()->create([
                        'modifier_id' => $modifier['id'],
                        'name' => $modifier['name'],
                        'price_delta' => $modifier['price_delta'],
                    ]);
                }
            }

            return $order->load('items.modifiers');
        });
    }

    /**
     * Take payment at the till: the order becomes a sale, and only here does
     * stock move.
     *
     * @param  array<string, mixed>  $payment
     */
    public function settle(Order $order, User $cashier, array $payment = []): Order
    {
        return DB::transaction(function () use ($order, $cashier, $payment) {
            // Re-read under a lock so a double-tap cannot settle twice.
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->guardTransition($locked, OrderStatus::Paid);

            $sale = $this->saleService->checkout([
                'items' => $this->checkoutItems($locked),
                'discount' => $payment['discount'] ?? 0,
                'payment_method' => $payment['payment_method'] ?? 'cash',
                'cash_received' => $payment['cash_received'] ?? null,
            ], $cashier);

            $locked->update([
                'status' => OrderStatus::Paid,
                'sale_id' => $sale->id,
                'cashier_id' => $cashier->id,
                'paid_at' => now(),
                'total' => $sale->total,
            ]);

            return $locked->fresh(['items.modifiers', 'sale']);
        });
    }

    public function reject(Order $order, User $user, string $reason): Order
    {
        return DB::transaction(function () use ($order, $user, $reason) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->guardTransition($locked, OrderStatus::Rejected);

            $locked->update([
                'status' => OrderStatus::Rejected,
                'reject_reason' => $reason,
                'rejected_by' => $user->id,
            ]);

            return $locked->fresh();
        });
    }

    public function markReady(Order $order): Order
    {
        return $this->advance($order, OrderStatus::Ready, ['ready_at' => now()]);
    }

    public function markCollected(Order $order): Order
    {
        return $this->advance($order, OrderStatus::Collected, ['collected_at' => now()]);
    }

    /**
     * Orders nobody came to pay for. Runs without a store in context, so it
     * sweeps every store at once — which is what a scheduled command wants.
     */
    public function expireStale(int $afterMinutes = 20): int
    {
        return Order::withoutGlobalScopes()
            ->where('status', OrderStatus::Placed->value)
            ->where('placed_at', '<', now()->subMinutes($afterMinutes))
            ->update(['status' => OrderStatus::Expired->value]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function advance(Order $order, OrderStatus $to, array $attributes = []): Order
    {
        return DB::transaction(function () use ($order, $to, $attributes) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->guardTransition($locked, $to);

            $locked->update([...$attributes, 'status' => $to]);

            return $locked->fresh(['items.modifiers']);
        });
    }

    private function guardTransition(Order $order, OrderStatus $to): void
    {
        if (! $order->status->canTransitionTo($to)) {
            throw new InvalidOrderTransitionException($order->status, $to);
        }
    }

    /**
     * The order's lines in the shape SaleService::checkout() expects, so the
     * price is recomputed from the menu rather than trusted from the order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function checkoutItems(Order $order): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, OrderItem> $items */
        $items = $order->items()->with('modifiers')->get();

        return $items->map(fn (OrderItem $item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'variant_id' => $item->product_variant_id,
            'modifier_ids' => $item->modifiers->pluck('modifier_id')->filter()->values()->all(),
        ])->all();
    }
}
