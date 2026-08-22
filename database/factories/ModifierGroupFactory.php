<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
{
    protected $model = ModifierGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Milk', 'Extras', 'Syrup', 'Temperature']).' '.fake()->numerify('##'),
            'min_select' => 0,
            'max_select' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function required(int $min = 1): static
    {
        return $this->state(fn (array $attributes) => ['min_select' => $min]);
    }

    public function multiSelect(int $max = 3): static
    {
        return $this->state(fn (array $attributes) => ['max_select' => $max]);
    }
}
