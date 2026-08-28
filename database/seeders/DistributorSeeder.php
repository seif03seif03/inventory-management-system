<?php

namespace Database\Seeders;

use App\Models\Distributor;
use Illuminate\Database\Seeder;

class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        $distributors = [
            ['Nile Retail Group', '+20225020001', 'purchasing@nileretail.test', 'Garden City, Cairo'],
            ['Alex Tech Stores', '+20345020002', 'orders@alextechstores.test', 'Stanley, Alexandria'],
            ['Delta Office Solutions', '+20405020003', 'supply@deltaoffice.test', 'Tanta, Gharbia'],
            ['Giza Business Supplies', '+20235020004', 'sales@gizabusiness.test', 'Mohandessin, Giza'],
            ['Canal Electronics', '+20645020005', 'orders@canalelectronics.test', 'Ismailia'],
            ['Upper Egypt IT', '+20885020006', 'procurement@upperegyptit.test', 'Assiut'],
        ];

        foreach ($distributors as [$name, $phone, $email, $address]) {
            Distributor::updateOrCreate(
                ['name' => $name],
                compact('phone', 'email', 'address') + ['active' => true]
            );
        }
    }
}
