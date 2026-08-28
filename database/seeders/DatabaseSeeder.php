<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,
            DistributorSeeder::class,
            ProductSeeder::class,
            DemoInventoryResetSeeder::class,
            StockInSeeder::class,
            StockOutSeeder::class,
            WarehouseTransferSeeder::class,
            InventoryAdjustmentSeeder::class,
        ]);
    }
}
