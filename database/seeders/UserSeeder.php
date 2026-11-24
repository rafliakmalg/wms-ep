<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            1 => 'Testing Admin',
            2 => 'Testing Supervisor',
            3 => 'Testing Operator',
        ];

        foreach ($users as $id => $name) {
            User::updateOrCreate(['id' => $id], ['name' => $name]);
        }
    }
}
