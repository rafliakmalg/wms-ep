<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            'pcs' => 'Pieces',
            'pack' => 'Pack',
            'box' => 'Box',
            'carton' => 'Carton',
            'kg' => 'Kilogram',
        ];

        foreach ($units as $code => $name) {
            Unit::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
