<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['name' => 'Main Warehouse', 'location' => '10th of Ramadan, Cairo', 'description' => 'Primary receiving and dispatch warehouse for Greater Cairo.', 'active' => true],
            ['name' => 'Alexandria Warehouse', 'location' => 'Smouha, Alexandria', 'description' => 'Regional warehouse serving Alexandria and Delta customers.', 'active' => true],
            ['name' => 'Nasr City Warehouse', 'location' => 'Nasr City, Cairo', 'description' => 'Fast-moving stock hub for east Cairo retail operations.', 'active' => true],
            ['name' => 'Giza Warehouse', 'location' => 'Dokki, Giza', 'description' => 'Secondary warehouse for west Cairo and Upper Egypt routes.', 'active' => true],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(['name' => $warehouse['name']], $warehouse);
        }
    }
}
