<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 80, 800);

        return [
            'queue_date' => today(),
            'queue_number' => fake()->unique()->numberBetween(1, 9999),
            'token' => Str::random(40),
            'customer_name' => fake()->firstName(),
            'status' => OrderStatus::Placed,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'item_count' => fake()->numberBetween(1, 5),
            'placed_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Ready,
            'paid_at' => now(),
            'ready_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Rejected,
            'reject_reason' => 'Sold out',
        ]);
    }

    public function placedAt(\DateTimeInterface $moment): static
    {
        return $this->state(fn (array $attributes) => ['placed_at' => $moment]);
    }
}
