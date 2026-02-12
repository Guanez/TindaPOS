<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 5000);
        $discount = fake()->randomFloat(2, 0, 50);
        $total = max(0, $subtotal - $discount);

        return [
            'receipt_number' => Sale::generateReceiptNumber(),
            'user_id' => User::factory(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'item_count' => fake()->numberBetween(1, 10),
            'payment_method' => fake()->randomElement(['cash', 'gcash', 'maya']),
            'cash_received' => $total + fake()->randomFloat(2, 0, 100),
            'change_amount' => fake()->randomFloat(2, 0, 100),
            'status' => 'completed',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'voided',
            'void_reason' => 'Test void',
            'voided_by' => User::factory(),
            'voided_at' => now(),
        ]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => now(),
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    public function gcash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'gcash',
        ]);
    }
}
