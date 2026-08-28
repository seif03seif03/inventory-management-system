<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Electronics' => 'Consumer electronics and smart devices for retail inventory.',
            'Computers' => 'Laptops, desktops, and business computing hardware.',
            'Mobile Phones' => 'Smartphones and mobile communication devices.',
            'Tablets' => 'Tablet computers and hybrid mobile devices.',
            'Accessories' => 'Peripheral devices and everyday technology accessories.',
            'Networking' => 'Routers, switches, access points, and network equipment.',
            'Storage Devices' => 'Internal and external storage products.',
            'Printers' => 'Office printing, scanning, and document equipment.',
            'Office Supplies' => 'Operational supplies for office and warehouse use.',
            'Cables & Adapters' => 'Power, display, data cables, and adapters.',
        ];

        foreach ($categories as $name => $description) {
            Category::updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'active' => true]
            );
        }
    }
}
