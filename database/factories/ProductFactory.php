<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 5, 500);

        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'barcode' => fake()->unique()->ean13(),
            'description' => fake()->sentence(),
            'cost_price' => $cost,
            'selling_price' => $cost * fake()->randomFloat(2, 1.1, 2.0),
            'stock_quantity' => fake()->numberBetween(10, 200),
            'low_stock_threshold' => 10,
            'is_favorite' => false,
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 3,
            'low_stock_threshold' => 10,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    public function withStock(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => $quantity,
        ]);
    }

    public function priced(float $cost, float $selling): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_price' => $cost,
            'selling_price' => $selling,
        ]);
    }
}
