<?php

namespace Database\Factories;

use App\Models\Handover;
use App\Models\HandoverItem;
use App\Models\Product;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandoverItem>
 */
class HandoverItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'handover_id' => Handover::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 1, 100),
            'rack_from_id' => Rack::factory(),
            'rack_to_id' => Rack::factory(),
        ];
    }
}
