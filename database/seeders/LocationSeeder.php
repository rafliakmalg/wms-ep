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
            'JKT' => [
                'address' => 'Jl. Gatot Subroto No. 18, Jakarta Selatan',
                'name' => 'Jakarta',
                'phone' => '021-5550100',
                'warehouses' => [
                    'GJP' => [
                        'address' => 'Jl. Kemayoran Gempol No. 5, Jakarta Pusat',
                        'name' => 'Gudang Jakarta Pusat',
                        'racks' => [
                            'Rak Laptop',
                            'Rak Smartphone',
                            'Rak Tablet',
                            'Rak Audio',
                            'Rak Monitor',
                            'Rak Jaringan',
                            'Rak Penyimpanan',
                            'Rak Aksesoris',
                        ],
                    ],
                    'GJT' => [
                        'address' => 'Jl. Pulogebang Raya No. 12, Jakarta Timur',
                        'name' => 'Gudang Jakarta Transit',
                        'racks' => [
                            'Rak Transit Masuk',
                            'Rak Transit Keluar',
                            'Rak Karantina',
                        ],
                    ],
                ],
            ],
            'SBY' => [
                'address' => 'Jl. Basuki Rahmat No. 40, Surabaya',
                'name' => 'Surabaya',
                'phone' => '031-5550200',
                'warehouses' => [
                    'GSB' => [
                        'address' => 'Jl. Rungkut Industri No. 21, Surabaya',
                        'name' => 'Gudang Surabaya',
                        'racks' => [
                            'Rak Laptop',
                            'Rak Smartphone',
                            'Rak Audio',
                            'Rak Monitor',
                            'Rak Aksesoris',
                            'Rak Penyimpanan',
                        ],
                    ],
                ],
            ],
            'BDG' => [
                'address' => 'Jl. Asia Afrika No. 77, Bandung',
                'name' => 'Bandung',
                'phone' => '022-5550300',
                'warehouses' => [
                    'GBD' => [
                        'address' => 'Jl. Soekarno Hatta No. 310, Bandung',
                        'name' => 'Gudang Bandung',
                        'racks' => [
                            'Rak Laptop',
                            'Rak Smartphone',
                            'Rak Aksesoris',
                            'Rak Jaringan',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($branches as $branchCode => $branchData) {
            $branch = Branch::firstOrCreate(['code' => $branchCode], [
                'address' => $branchData['address'],
                'name' => $branchData['name'],
                'phone' => $branchData['phone'],
            ]);

            foreach ($branchData['warehouses'] as $warehouseCode => $warehouseData) {
                $warehouse = Warehouse::firstOrCreate(['code' => $warehouseCode], [
                    'address' => $warehouseData['address'],
                    'branch_id' => $branch->id,
                    'name' => $warehouseData['name'],
                ]);

                foreach ($warehouseData['racks'] as $index => $rackName) {
                    Rack::firstOrCreate([
                        'code' => sprintf('%s-%03d', $warehouseCode, $index + 1),
                        'warehouse_id' => $warehouse->id,
                    ], ['name' => $rackName]);
                }
            }
        }
    }
}
