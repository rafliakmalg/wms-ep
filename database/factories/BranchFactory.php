<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'address' => fake()->address(),
            'code' => fake()->unique()->bothify('BR-###'),
            'name' => fake()->unique()->city(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
