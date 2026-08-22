<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modifier>
 */
class ModifierFactory extends Factory
{
    protected $model = Modifier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modifier_group_id' => ModifierGroup::factory(),
            'name' => fake()->randomElement(['Oat milk', 'Soy milk', 'Extra shot', 'Vanilla']),
            'price_delta' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function costing(float $delta): static
    {
        return $this->state(fn (array $attributes) => ['price_delta' => $delta]);
    }
}
