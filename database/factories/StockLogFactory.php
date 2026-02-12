<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLog>
 */
class StockLogFactory extends Factory
{
    protected $model = StockLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $change = fake()->numberBetween(1, 50);

        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'sale_id' => null,
            'type' => fake()->randomElement(['restock', 'adjustment', 'sale']),
            'quantity_change' => $change,
            'stock_before' => 100,
            'stock_after' => 100 + $change,
            'reason' => fake()->sentence(),
        ];
    }
}
