<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'type' => 'sari_sari',
            'address' => fake()->address(),
            'phone' => fake()->numerify('09##-###-####'),
            'currency_symbol' => 'P',
            'online_ordering_enabled' => false,
            'is_active' => true,
        ];
    }

    public function cafe(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'cafe']);
    }

    public function withOnlineOrdering(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cafe',
            'online_ordering_enabled' => true,
        ]);
    }
}
