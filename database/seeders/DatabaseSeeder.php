<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            LocationSeeder::class,
            UnitSeeder::class,
            ProductSeeder::class,
            AdjustmentSeeder::class,
        ]);
    }
}
