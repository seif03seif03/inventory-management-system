<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed realistic demo user accounts.
     * Password for every seeded user: password
     */
    public function run(): void
    {
        $roles = Role::pluck('id', 'name');

        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@inventory.test',
                'phone' => '+201001110001',
                'role' => 'Admin',
                'receive_notifications' => true,
            ],
            [
                'name' => 'Warehouse Manager',
                'email' => 'manager@inventory.test',
                'phone' => '+201001110002',
                'role' => 'Warehouse Manager',
                'receive_notifications' => true,
            ],
            [
                'name' => 'Warehouse Employee',
                'email' => 'warehouse@inventory.test',
                'phone' => '+201001110003',
                'role' => 'Warehouse Employee',
                'receive_notifications' => false,
            ],
            [
                'name' => 'Inventory Employee',
                'email' => 'inventory@inventory.test',
                'phone' => '+201001110004',
                'role' => 'Inventory Employee',
                'receive_notifications' => true,
            ],
            [
                'name' => 'Inventory Viewer',
                'email' => 'viewer@inventory.test',
                'phone' => null,
                'role' => 'Viewer',
                'receive_notifications' => false,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'phone' => $user['phone'],
                    'password' => Hash::make('password'),
                    'role_id' => $roles[$user['role']] ?? null,
                    'receive_notifications' => $user['receive_notifications'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
