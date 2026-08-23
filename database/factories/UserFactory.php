<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Faker's userName() can contain dots, which the staff form
            // rejects — test accounts should look like ones a cafe could
            // actually create.
            'username' => fake()->unique()->regexify('[a-z]{5,10}(_[a-z]{3,6})?'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Cashier,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A platform admin: the super_admin role AND no store.
     *
     * The store has to be cleared after the fact — BelongsToStore stamps the
     * store in context on create and cannot tell "not given" from "given as
     * null" — so this state is applied with afterCreating rather than by
     * setting store_id in the attributes, where it would be overwritten.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SuperAdmin,
        ])->afterCreating(function (User $user): void {
            $user->forceFill(['store_id' => null])->save();
        });
    }

    /**
     * Create an owner user.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Owner,
        ]);
    }

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Create a cashier user.
     */
    public function cashier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Cashier,
        ]);
    }

    /**
     * Create an inactive user.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
