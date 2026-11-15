<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'address' => fake()->address(),
            'code' => fake()->unique()->bothify('WH-###'),
            'name' => 'Gudang '.fake()->unique()->word(),
        ];
    }
}
