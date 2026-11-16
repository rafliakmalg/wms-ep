<?php

namespace Database\Factories;

use App\Models\Movement;
use App\Models\Product;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 1, 100),
            'rack_from_id' => null,
            'rack_to_id' => Rack::factory(),
        ];
    }
}
