<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Change this password immediately in production environments.
        User::firstOrCreate(
            ['email' => 'admin@dev.com'],
            [
                'name' => 'MayWrites Admin',
                'password' => Hash::make('admin@dev.com'),
                'role' => UserRole::Admin,
            ],
        );
        User::firstOrCreate(
            ['email' => 'user@dev.com'],
            [
                'name' => 'MayWrites Customer',
                'password' => Hash::make('user@dev.com'),
                'role' => UserRole::Customer,
            ],
        );
    }
}
