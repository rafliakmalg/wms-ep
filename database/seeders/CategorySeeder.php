<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Laptop',
            'Smartphone',
            'Tablet',
            'Audio',
            'Monitor',
            'Jaringan',
            'Penyimpanan',
            'Aksesoris',
            'Printer',
        ];

        foreach ($names as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
