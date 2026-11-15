<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'code' => fake()->unique()->bothify('A-##-##'),
            'name' => fake()->words(2, true),
        ];
    }
}
