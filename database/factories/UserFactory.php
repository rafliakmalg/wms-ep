<?php

namespace Database\Factories;

use App\Models\Role;
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

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin'])->id,
        ]);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'username' => fake()->unique()->bothify('user_??##'),
        ];
    }
}
