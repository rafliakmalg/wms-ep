<?php

namespace Database\Factories;

use App\Models\Handover;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Handover>
 */
class HandoverFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ST-'.fake()->unique()->numerify('######'),
            'custody_id' => User::factory(),
            'note' => fake()->sentence(),
        ];
    }
}
