<?php

namespace Database\Factories;

use App\Models\Adjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Adjustment>
 */
class AdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PN-'.fake()->unique()->numerify('######'),
            'note' => fake()->sentence(),
        ];
    }
}
