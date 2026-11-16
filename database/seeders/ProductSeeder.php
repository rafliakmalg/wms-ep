<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $units = Unit::query()->pluck('id', 'code');
        $categories = Category::query()->pluck('id', 'name');

        $products = [
            ['LTP-001', 'ASUS Vivobook 14 A1405', 'Laptop', 'UNIT', 5, ['DUS' => 5]],
            ['LTP-002', 'Lenovo IdeaPad Slim 3 15IAH8', 'Laptop', 'UNIT', 5, ['DUS' => 5]],
            ['LTP-003', 'HP Pavilion 14-dv2', 'Laptop', 'UNIT', 5, ['DUS' => 5]],
            ['LTP-004', 'Acer Aspire 5 A515-57', 'Laptop', 'UNIT', 5, ['DUS' => 5]],
            ['LTP-005', 'Apple MacBook Air 13 M2 256GB', 'Laptop', 'UNIT', 3, []],

            ['SMP-001', 'Samsung Galaxy A55 5G 8/256GB', 'Smartphone', 'UNIT', 10, ['DUS' => 10]],
            ['SMP-002', 'Xiaomi Redmi Note 13 8/256GB', 'Smartphone', 'UNIT', 10, ['DUS' => 10]],
            ['SMP-003', 'Apple iPhone 15 128GB', 'Smartphone', 'UNIT', 10, []],
            ['SMP-004', 'OPPO Reno11 F 5G 8/256GB', 'Smartphone', 'UNIT', 10, ['DUS' => 10]],
            ['SMP-005', 'Vivo V30e 5G 8/256GB', 'Smartphone', 'UNIT', 10, ['DUS' => 10]],

            ['TAB-001', 'Apple iPad 10th Gen 64GB WiFi', 'Tablet', 'UNIT', 5, []],
            ['TAB-002', 'Samsung Galaxy Tab A9+ 8/128GB', 'Tablet', 'UNIT', 5, ['DUS' => 5]],
            ['TAB-003', 'Lenovo Tab M10 Plus Gen 3', 'Tablet', 'UNIT', 5, ['DUS' => 5]],

            ['AUD-001', 'Sony WH-CH520 Headphone Bluetooth', 'Audio', 'PCS', 10, ['BOX' => 10]],
            ['AUD-002', 'JBL Flip 6 Portable Speaker', 'Audio', 'PCS', 10, ['BOX' => 6]],
            ['AUD-003', 'Apple AirPods Pro 2nd Gen USB-C', 'Audio', 'PCS', 10, []],
            ['AUD-004', 'Samsung Galaxy Buds2 Pro', 'Audio', 'PCS', 10, ['BOX' => 10]],
            ['AUD-005', 'Logitech Z313 Speaker 2.1', 'Audio', 'PCS', 5, ['BOX' => 4]],

            ['MON-001', 'LG UltraGear 24GN60R 24 Inch', 'Monitor', 'UNIT', 5, ['DUS' => 4]],
            ['MON-002', 'Dell P2423H 24 Inch IPS', 'Monitor', 'UNIT', 5, ['DUS' => 4]],
            ['MON-003', 'Samsung Odyssey G5 27 Inch 144Hz', 'Monitor', 'UNIT', 5, ['DUS' => 4]],
            ['MON-004', 'AOC 24B1XHS 24 Inch IPS', 'Monitor', 'UNIT', 5, ['DUS' => 4]],

            ['NET-001', 'TP-Link Archer C6 AC1200 Router', 'Jaringan', 'PCS', 10, ['BOX' => 10]],
            ['NET-002', 'Tenda AC10 AC1200 Router', 'Jaringan', 'PCS', 10, ['BOX' => 10]],
            ['NET-003', 'Mikrotik hAP ac2 Router', 'Jaringan', 'PCS', 10, ['BOX' => 10]],
            ['NET-004', 'D-Link DES-1016A 16-Port Switch', 'Jaringan', 'PCS', 5, ['BOX' => 5]],
            ['NET-005', 'Ubiquiti UniFi U6 Lite Access Point', 'Jaringan', 'PCS', 5, ['BOX' => 5]],

            ['STO-001', 'SanDisk Ultra Flair 128GB USB 3.0', 'Penyimpanan', 'PCS', 30, [
                'BOX' => 10,
                'DUS' => 100,
            ]],
            ['STO-002', 'Samsung T7 Portable SSD 1TB', 'Penyimpanan', 'PCS', 15, ['BOX' => 10]],
            ['STO-003', 'WD Elements 2TB Portable HDD', 'Penyimpanan', 'PCS', 15, ['BOX' => 10]],
            ['STO-004', 'Kingston A400 480GB SSD SATA', 'Penyimpanan', 'PCS', 20, ['BOX' => 10]],
            ['STO-005', 'Seagate Barracuda 1TB HDD', 'Penyimpanan', 'PCS', 15, ['BOX' => 10]],

            ['ACC-001', 'Logitech M331 Silent Mouse Wireless', 'Aksesoris', 'PCS', 30, [
                'BOX' => 10,
                'DUS' => 50,
            ]],
            ['ACC-002', 'Logitech K380 Keyboard Bluetooth', 'Aksesoris', 'PCS', 20, ['BOX' => 10]],
            ['ACC-003', 'Anker 20W USB-C Charger Nano', 'Aksesoris', 'PCS', 30, [
                'BOX' => 10,
                'DUS' => 100,
            ]],
            ['ACC-004', 'Baseus Kabel USB-C to USB-C 1m', 'Aksesoris', 'PCS', 50, [
                'PAK' => 5,
                'BOX' => 50,
            ]],
            ['ACC-005', 'Anker PowerCore 10000 Powerbank', 'Aksesoris', 'PCS', 25, ['BOX' => 10]],
            ['ACC-006', 'Logitech MK295 Keyboard Mouse Combo', 'Aksesoris', 'SET', 15, ['BOX' => 6]],

            ['PRN-001', 'Canon PIXMA G2010 Ink Tank', 'Printer', 'UNIT', 5, []],
            ['PRN-002', 'Epson EcoTank L3210', 'Printer', 'UNIT', 5, []],
            ['PRN-003', 'HP DeskJet Ink Advantage 2336', 'Printer', 'UNIT', 5, []],
        ];

        foreach ($products as [$sku, $name, $category, $baseUnit, $minStock, $extraUnits]) {
            $product = Product::firstOrCreate(['sku' => $sku], [
                'base_unit_id' => $units[$baseUnit],
                'category_id' => $categories[$category],
                'min_stock' => $minStock,
                'name' => $name,
            ]);

            foreach ($extraUnits as $unitCode => $factor) {
                $product->productUnits()->firstOrCreate(['unit_id' => $units[$unitCode]], ['factor' => $factor]);
            }
        }
    }
}
