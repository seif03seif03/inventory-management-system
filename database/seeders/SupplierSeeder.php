<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['Tech Source Egypt', '+20224010001', 'sales@techsource-eg.test', 'Maadi Technology Park, Cairo'],
            ['Cairo Electronics', '+20224010002', 'orders@cairo-electronics.test', 'Ramses Street, Cairo'],
            ['Future Systems', '+20224010003', 'supply@future-systems.test', 'Smart Village, Giza'],
            ['Digital Supply Co.', '+20224010004', 'procurement@digitalsupply.test', 'Heliopolis, Cairo'],
            ['Smart Tech Distribution', '+20224010005', 'wholesale@smarttechdist.test', 'Borg El Arab, Alexandria'],
            ['Egyptian IT Supplies', '+20224010006', 'contact@egyptianit.test', 'Mansoura, Dakahlia'],
            ['Delta Electronics', '+20224010007', 'sales@delta-electronics.test', 'Tanta, Gharbia'],
            ['Modern Hardware', '+20224010008', 'orders@modernhardware.test', '6th of October City, Giza'],
        ];

        foreach ($suppliers as [$name, $phone, $email, $address]) {
            Supplier::updateOrCreate(
                ['name' => $name],
                compact('phone', 'email', 'address') + ['active' => true]
            );
        }
    }
}
