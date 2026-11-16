<?php

namespace Database\Seeders;

use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Rack;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdjustmentSeeder extends Seeder
{
    /**
     * Kuantitas awal per barang: [Jakarta Pusat, Surabaya, Bandung, Jakarta Transit].
     */
    private const STOCK = [
        'LTP-001' => [24, 10, 0, 0],
        'LTP-002' => [18, 8, 6, 0],
        'LTP-003' => [15, 0, 5, 0],
        'LTP-004' => [20, 12, 0, 4],
        'LTP-005' => [9, 4, 0, 0],
        'SMP-001' => [60, 25, 12, 0],
        'SMP-002' => [80, 30, 0, 10],
        'SMP-003' => [30, 12, 8, 0],
        'SMP-004' => [45, 0, 0, 0],
        'SMP-005' => [40, 18, 10, 0],
        'TAB-001' => [14, 0, 0, 0],
        'TAB-002' => [22, 0, 0, 0],
        'TAB-003' => [16, 0, 0, 0],
        'AUD-001' => [50, 20, 0, 0],
        'AUD-002' => [35, 15, 0, 0],
        'AUD-003' => [25, 10, 0, 0],
        'AUD-004' => [28, 0, 0, 0],
        'AUD-005' => [18, 8, 0, 0],
        'MON-001' => [20, 8, 0, 0],
        'MON-002' => [16, 6, 0, 0],
        'MON-003' => [12, 0, 0, 0],
        'MON-004' => [25, 10, 0, 0],
        'NET-001' => [70, 0, 20, 0],
        'NET-002' => [55, 0, 15, 0],
        'NET-003' => [30, 0, 10, 0],
        'NET-004' => [18, 0, 0, 0],
        'NET-005' => [12, 0, 0, 0],
        'STO-001' => [200, 80, 0, 0],
        'STO-002' => [40, 15, 0, 0],
        'STO-003' => [35, 12, 0, 0],
        'STO-004' => [60, 25, 0, 0],
        'STO-005' => [45, 0, 0, 0],
        'ACC-001' => [120, 50, 30, 0],
        'ACC-002' => [70, 30, 15, 0],
        'ACC-003' => [150, 60, 40, 20],
        'ACC-004' => [300, 120, 80, 0],
        'ACC-005' => [90, 35, 25, 0],
        'ACC-006' => [45, 20, 10, 0],
        'PRN-001' => [14, 0, 0, 0],
        'PRN-002' => [16, 0, 0, 0],
        'PRN-003' => [10, 0, 0, 0],
    ];

    /**
     * Kode gudang => [kolom di STOCK, hari lalu, nama gudang, nomor rak per kategori].
     * Gudang transit menyimpan semua barang di rak pertama.
     */
    private const WAREHOUSES = [
        'GJP' => [0, 7, 'Gudang Jakarta Pusat', [
            'Laptop' => 1,
            'Smartphone' => 2,
            'Tablet' => 3,
            'Audio' => 4,
            'Monitor' => 5,
            'Jaringan' => 6,
            'Penyimpanan' => 7,
            'Aksesoris' => 8,
            'Printer' => 8,
        ]],
        'GSB' => [1, 6, 'Gudang Surabaya', [
            'Laptop' => 1,
            'Smartphone' => 2,
            'Audio' => 3,
            'Monitor' => 4,
            'Aksesoris' => 5,
            'Penyimpanan' => 6,
        ]],
        'GBD' => [2, 5, 'Gudang Bandung', [
            'Laptop' => 1,
            'Smartphone' => 2,
            'Aksesoris' => 3,
            'Jaringan' => 4,
        ]],
        'GJT' => [3, 4, 'Gudang Jakarta Transit', []],
    ];

    public function run(): void
    {
        $userId = User::query()->orderBy('id')->firstOrFail()->id;
        $products = Product::query()->with('category')->get()->keyBy('sku');

        foreach (self::WAREHOUSES as $warehouseCode => [$column, $daysAgo, $warehouseName, $rackNumbers]) {
            $note = 'Stok awal '.$warehouseName;

            if (Adjustment::query()->where('note', $note)->exists()) {
                continue;
            }

            $racks = Rack::query()
                ->whereHas('warehouse', fn ($query) => $query->where('code', $warehouseCode))
                ->pluck('id', 'code');

            $items = [];

            foreach (self::STOCK as $sku => $quantities) {
                $quantity = $quantities[$column];

                if ($quantity === 0) {
                    continue;
                }

                $rackNumber = $rackNumbers[$products[$sku]->category->name] ?? 1;

                $items[] = [
                    'product_id' => $products[$sku]->id,
                    'quantity' => number_format($quantity, 3, '.', ''),
                    'rack_id' => $racks[sprintf('%s-%03d', $warehouseCode, $rackNumber)],
                ];
            }

            DB::transaction(function () use ($daysAgo, $items, $note, $userId) {
                $createdAt = now()->subDays($daysAgo);

                $adjustment = Adjustment::query()->forceCreate([
                    'code' => (string) Str::uuid(),
                    'created_at' => $createdAt,
                    'created_by_id' => $userId,
                    'note' => $note,
                    'updated_at' => $createdAt,
                    'updated_by_id' => $userId,
                ]);

                $adjustment->update(['code' => sprintf('PN-%06d', $adjustment->id)]);

                $changes = [];

                foreach ($items as $item) {
                    $adjustment->items()->forceCreate([
                        ...$item,
                        'created_at' => $createdAt,
                        'created_by_id' => $userId,
                        'reason' => AdjustmentItem::REASON_CORRECTION,
                        'updated_at' => $createdAt,
                        'updated_by_id' => $userId,
                    ]);

                    $adjustment->movements()->forceCreate([
                        'created_at' => $createdAt,
                        'created_by_id' => $userId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'rack_to_id' => $item['rack_id'],
                        'updated_at' => $createdAt,
                        'updated_by_id' => $userId,
                    ]);

                    $changes[] = [
                        'path' => 'quantity',
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'rack_id' => $item['rack_id'],
                    ];
                }

                Inventory::applyChanges($changes, $userId);
            }, attempts: 5);
        }
    }
}
