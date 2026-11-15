<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'supervisor', 'operator'] as $role) {
            User::firstOrCreate(['username' => "testing_{$role}"], [
                'name' => 'Testing '.ucfirst($role),
                'password' => 'admin123',
                'role_id' => Role::where('name', $role)->value('id'),
            ]);
        }
    }
}
