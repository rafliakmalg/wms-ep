<?php

namespace Database\Factories;

use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Product;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdjustmentItem>
 */
class AdjustmentItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'adjustment_id' => Adjustment::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 1, 100),
            'rack_id' => Rack::factory(),
            'reason' => AdjustmentItem::REASON_FOUND,
        ];
    }
}
