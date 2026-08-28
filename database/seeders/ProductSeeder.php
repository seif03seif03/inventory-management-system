<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        $products = [
            ['Mobile Phones', 'iPhone 15 128GB', 'APL-IP15-128', '622300100001', 42999, 12],
            ['Mobile Phones', 'iPhone 15 Pro 256GB', 'APL-IP15P-256', '622300100002', 61999, 10],
            ['Mobile Phones', 'Samsung Galaxy S24', 'SAM-S24-256', '622300100003', 38999, 14],
            ['Mobile Phones', 'Samsung Galaxy A55', 'SAM-A55-128', '622300100004', 18999, 18],
            ['Mobile Phones', 'Xiaomi Redmi Note 13 Pro', 'XIA-RN13P-256', '622300100005', 15499, 16],
            ['Computers', 'MacBook Air M3 13-inch', 'APL-MBA-M3-13', '622300100006', 68999, 8],
            ['Computers', 'Dell Latitude 5450', 'DEL-LAT-5450', '622300100007', 44999, 10],
            ['Computers', 'HP ProBook 450 G10', 'HP-PB450-G10', '622300100008', 39999, 10],
            ['Computers', 'Lenovo ThinkPad E14 Gen 5', 'LEN-TPE14-G5', '622300100009', 36999, 10],
            ['Computers', 'Asus ExpertBook B1', 'ASU-EXB1-15', '622300100010', 29999, 9],
            ['Tablets', 'iPad Air 11-inch', 'APL-IPADAIR-11', '622300100011', 32999, 8],
            ['Tablets', 'Samsung Galaxy Tab S9 FE', 'SAM-TABS9FE', '622300100012', 23999, 8],
            ['Tablets', 'Lenovo Tab M11', 'LEN-TAB-M11', '622300100013', 8999, 12],
            ['Storage Devices', 'Kingston NV2 1TB SSD', 'KIN-NV2-1TB', '622300100014', 3399, 20],
            ['Storage Devices', 'Samsung 980 1TB SSD', 'SAM-980-1TB', '622300100015', 4599, 20],
            ['Storage Devices', 'WD Blue 2TB HDD', 'WD-BLUE-2TB', '622300100016', 2999, 18],
            ['Storage Devices', 'SanDisk Extreme 1TB Portable SSD', 'SAN-EXT-1TB', '622300100017', 6299, 12],
            ['Networking', 'TP-Link Archer AX55 Router', 'TPL-AX55', '622300100018', 4999, 12],
            ['Networking', 'Cisco CBS350 24-Port Switch', 'CIS-CBS350-24', '622300100019', 21999, 6],
            ['Networking', 'Ubiquiti UniFi U6+ Access Point', 'UBQ-U6PLUS', '622300100020', 6999, 8],
            ['Networking', 'D-Link 8-Port Gigabit Switch', 'DLK-DGS108', '622300100021', 1299, 15],
            ['Accessories', 'Logitech M650 Wireless Mouse', 'LOG-M650', '622300100022', 1499, 25],
            ['Accessories', 'Logitech K380 Keyboard', 'LOG-K380', '622300100023', 1799, 25],
            ['Accessories', 'Anker PowerCore 20000 Power Bank', 'ANK-PC20000', '622300100024', 2499, 16],
            ['Accessories', 'JBL Tune 520BT Headphones', 'JBL-T520BT', '622300100025', 2299, 15],
            ['Electronics', 'Dell 24-inch Monitor P2422H', 'DEL-P2422H', '622300100026', 8999, 10],
            ['Electronics', 'LG 27-inch Monitor 27MP400', 'LG-27MP400', '622300100027', 7499, 10],
            ['Printers', 'HP LaserJet Pro M404dn', 'HP-M404DN', '622300100028', 15999, 5],
            ['Printers', 'Canon imageCLASS MF272dw', 'CAN-MF272DW', '622300100029', 13499, 5],
            ['Printers', 'Epson EcoTank L3250', 'EPS-L3250', '622300100030', 11999, 6],
            ['Cables & Adapters', 'Belkin USB-C Cable 1m', 'BEL-USBC-1M', '622300100031', 399, 40],
            ['Cables & Adapters', 'Anker USB-C to HDMI Adapter', 'ANK-USBC-HDMI', '622300100032', 899, 25],
            ['Cables & Adapters', 'UGREEN HDMI 2.1 Cable 2m', 'UGR-HDMI21-2M', '622300100033', 499, 35],
            ['Cables & Adapters', 'Apple USB-C 20W Power Adapter', 'APL-20W-USBC', '622300100034', 1199, 25],
            ['Office Supplies', 'Brother Label Printer QL-800', 'BRO-QL800', '622300100035', 8999, 5],
            ['Office Supplies', 'Zebra ZD220 Barcode Printer', 'ZEB-ZD220', '622300100036', 13999, 4],
            ['Office Supplies', 'A4 Copy Paper Box', 'PPR-A4-BOX', '622300100037', 1199, 30],
            ['Office Supplies', 'Thermal Barcode Labels 100x50', 'LBL-10050-ROLL', '622300100038', 349, 30],
            ['Accessories', 'Microsoft Modern Webcam', 'MS-WEBCAM-HD', '622300100039', 2499, 12],
            ['Accessories', 'Logitech C920s Webcam', 'LOG-C920S', '622300100040', 4499, 10],
            ['Electronics', 'APC Back-UPS 1200VA', 'APC-BX1200', '622300100041', 6999, 8],
            ['Electronics', 'Eaton 5E 850VA UPS', 'EAT-5E850', '622300100042', 3999, 8],
            ['Networking', 'MikroTik hAP ax2 Router', 'MTK-HAPAX2', '622300100043', 4299, 9],
            ['Storage Devices', 'Seagate Expansion 4TB HDD', 'SEA-EXP-4TB', '622300100044', 4999, 10],
            ['Computers', 'Acer Aspire 5 15-inch', 'ACE-ASP5-15', '622300100045', 24999, 10],
            ['Computers', 'MSI Modern 14', 'MSI-MOD14', '622300100046', 27999, 9],
            ['Mobile Phones', 'Oppo Reno 11F', 'OPP-REN11F', '622300100047', 13999, 14],
            ['Mobile Phones', 'Realme 12 Pro', 'RLM-12PRO', '622300100048', 16999, 12],
            ['Tablets', 'Huawei MatePad 11.5', 'HUA-MP115', '622300100049', 16999, 7],
            ['Printers', 'Brother HL-L2365DW', 'BRO-HLL2365', '622300100050', 9999, 5],
        ];

        foreach ($products as [$category, $name, $sku, $barcode, $price, $minimumStock]) {
            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $categories[$category],
                    'name' => $name,
                    'barcode' => $barcode,
                    'description' => "Demo inventory item: {$name}.",
                    'price' => $price,
                    'minimum_stock' => $minimumStock,
                    'active' => true,
                ]
            );
        }
    }
}
