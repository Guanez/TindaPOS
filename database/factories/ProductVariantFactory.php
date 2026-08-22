<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->unique()->randomElement(['8oz', '12oz', '16oz', '22oz']),
            'cost_price' => 25,
            'selling_price' => 120,
            'is_default' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function priced(float $selling, ?float $cost = null): static
    {
        return $this->state(fn (array $attributes) => [
            'selling_price' => $selling,
            'cost_price' => $cost ?? $attributes['cost_price'],
        ]);
    }
}
