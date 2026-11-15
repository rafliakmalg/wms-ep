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
        $pcs = Unit::where('code', 'pcs')->first();
        $box = Unit::where('code', 'box')->first();
        $carton = Unit::where('code', 'carton')->first();

        $products = [
            ['SKU-0001', 'Kabel USB-C 1m', 'Elektronik', 20, [[$box, 10], [$carton, 100]]],
            ['SKU-0002', 'Pulpen Hitam', 'Alat Tulis', 50, [[$box, 12]]],
            ['SKU-0003', 'Buku Tulis A5', 'Alat Tulis', 30, []],
        ];

        foreach ($products as [$sku, $name, $category, $minStock, $units]) {
            $product = Product::firstOrCreate(['sku' => $sku], [
                'base_unit_id' => $pcs->id,
                'category_id' => Category::where('name', $category)->value('id'),
                'min_stock' => $minStock,
                'name' => $name,
            ]);

            foreach ($units as [$unit, $factor]) {
                $product->productUnits()->firstOrCreate(['unit_id' => $unit->id], ['factor' => $factor]);
            }
        }
    }
}
