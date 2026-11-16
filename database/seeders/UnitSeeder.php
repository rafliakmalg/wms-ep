<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['PCS', 'UNIT', 'SET', 'PAK', 'BOX', 'DUS', 'ROLL', 'MTR', 'KG'] as $code) {
            Unit::firstOrCreate(['code' => $code], ['name' => $code]);
        }
    }
}
