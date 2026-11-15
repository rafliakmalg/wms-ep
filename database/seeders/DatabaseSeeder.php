<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, CategorySeeder::class, LocationSeeder::class, UnitSeeder::class, ProductSeeder::class]);

        User::factory()->create([
            'email' => 'admin@example.com',
            'name' => 'Admin',
            'role_id' => Role::where('name', 'admin')->value('id'),
        ]);
    }
}
