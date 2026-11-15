<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Rack;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            'JKT' => ['Jakarta', [
                'JKT-01' => 'Gudang Utama Jakarta',
                'JKT-02' => 'Gudang Transit Jakarta',
            ]],
            'SBY' => ['Surabaya', ['SBY-01' => 'Gudang Utama Surabaya']],
        ];

        foreach ($branches as $branchCode => [$branchName, $warehouses]) {
            $branch = Branch::firstOrCreate(['code' => $branchCode], ['name' => $branchName]);

            foreach ($warehouses as $warehouseCode => $warehouseName) {
                $warehouse = Warehouse::firstOrCreate(
                    ['code' => $warehouseCode],
                    [
                        'branch_id' => $branch->id,
                        'name' => $warehouseName,
                    ],
                );

                foreach (['A-01', 'A-02', 'B-01'] as $rackCode) {
                    Rack::firstOrCreate([
                        'code' => $rackCode,
                        'warehouse_id' => $warehouse->id,
                    ]);
                }
            }
        }
    }
}
