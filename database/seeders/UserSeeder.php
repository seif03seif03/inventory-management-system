<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed default development user accounts.
     *
     * Credentials for local development:
     * - Admin:    admin@example.com    / password123
     * - Manager:  manager@example.com  / password123
     * - Employee: employee@example.com / password123
     */
    public function run(): void
    {
        $adminRole    = Role::where('name', 'Admin')->first();
        $managerRole  = Role::where('name', 'Warehouse Manager')->first();
        $employeeRole = Role::where('name', 'Warehouse Employee')->first();

        // 1. Admin account
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password123'),
                'role_id'  => $adminRole?->id,
            ]
        );

        // 2. Warehouse Manager account
        User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name'     => 'Warehouse Manager',
                'password' => Hash::make('password123'),
                'role_id'  => $managerRole?->id,
            ]
        );

        // 3. Warehouse Employee account
        User::firstOrCreate(
            ['email' => 'employee@example.com'],
            [
                'name'     => 'Warehouse Employee',
                'password' => Hash::make('password123'),
                'role_id'  => $employeeRole?->id,
            ]
        );
    }
}
