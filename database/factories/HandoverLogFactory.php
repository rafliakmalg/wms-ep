<?php

namespace Database\Factories;

use App\Models\Handover;
use App\Models\HandoverLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandoverLog>
 */
class HandoverLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => HandoverLog::ACTION_TAKE,
            'custody_from_id' => null,
            'custody_to_id' => User::factory(),
            'handover_id' => Handover::factory(),
        ];
    }
}
