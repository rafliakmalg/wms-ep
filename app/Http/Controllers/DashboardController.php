<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Models\Adjustment;
use App\Models\Category;
use App\Models\Handover;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class DashboardController extends Controller
{
    private const ACTIVITY_DAYS = 7;

    private const CATEGORY_LIMIT = 5;

    private const LIST_LIMIT = 8;

    private const LOCAL_TIMEZONE = 'Asia/Jakarta';

    private const RECENT_DAYS = 30;

    private const STOCK_SUBQUERY = 'coalesce((select sum(quantity) from inventories where inventories.product_id = products.id), 0)';

    /**
     * @return Collection<int, Product>
     */
    private function lowStock(): Collection
    {
        return $this->lowStockQuery()
            ->with('baseUnit')
            ->withSum('inventories as total_quantity', 'quantity')
            ->orderByRaw(self::STOCK_SUBQUERY.' - products.min_stock')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /**
     * @return Builder<Product>
     */
    private function lowStockQuery(): Builder
    {
        return Product::query()
            ->where('active', true)
            ->whereRaw(self::STOCK_SUBQUERY.' < products.min_stock');
    }

    /**
     * Jumlah pergerakan per hari (hari lokal, bukan UTC) untuk beberapa hari terakhir.
     *
     * @return array<int, array{date: string, total: int}>
     */
    private function movementsPerDay(): array
    {
        $days = [];

        foreach (range(self::ACTIVITY_DAYS - 1, 0) as $daysAgo) {
            $start = now(self::LOCAL_TIMEZONE)->subDays($daysAgo)->startOfDay();
            $end = $start->clone()->endOfDay();

            $days[] = [
                'date' => $start->toDateString(),
                'total' => Movement::query()->whereBetween('created_at', [$start->utc(), $end->utc()])->count(),
            ];
        }

        return $days;
    }

    /**
     * @return Collection<int, Movement>
     */
    private function recentMovements(): Collection
    {
        return Movement::query()
            ->with(['adjustment', 'handover', 'product', 'rackFrom.warehouse', 'rackTo.warehouse'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    #[Authorize('dashboard.read')]
    public function show(Request $request): DashboardResource
    {
        $takenHandovers = $this->takenHandovers($request);

        return DashboardResource::make([
            'low_stock' => $this->lowStock(),
            'low_stock_total' => $this->lowStockQuery()->count(),
            'movements_per_day' => $this->movementsPerDay(),
            'recent_adjustments' => Adjustment::query()
                ->where('status', Adjustment::STATUS_ACTIVE)
                ->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))
                ->count(),
            'recent_movements' => $this->recentMovements(),
            'stock_by_category' => $this->stockByCategory(),
            'stock_by_warehouse' => $this->stockByWarehouse(),
            'taken_handovers' => $takenHandovers->clone()->with('custody')->latest('id')->limit(self::LIST_LIMIT)->get(),
            'taken_handovers_total' => $takenHandovers->count(),
            'total_products' => Product::query()->where('active', true)->count(),
            'total_quantity' => Quantity::format(Inventory::query()->sum('quantity')),
        ]);
    }

    /**
     * Kategori terbesar apa adanya, sisanya digabung menjadi satu baris "Lainnya".
     *
     * @return array<int, array{id: int|null, name: string, total_quantity: string}>
     */
    private function stockByCategory(): array
    {
        $categories = Category::query()
            ->select('categories.*')
            ->selectRaw('coalesce(sum(inventories.quantity), 0) as total_quantity')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->leftJoin('inventories', 'inventories.product_id', '=', 'products.id')
            ->groupBy('categories.id')
            ->orderByDesc('total_quantity')
            ->get();

        $rows = $categories->take(self::CATEGORY_LIMIT)->map(fn (Category $category) => [
            'id' => $category->id,
            'name' => $category->name,
            'total_quantity' => Quantity::format($category->total_quantity),
        ])->all();

        $others = $categories->slice(self::CATEGORY_LIMIT)->sum('total_quantity');

        if ($others > 0) {
            $rows[] = [
                'id' => null,
                'name' => __('index.dashboard.other_categories'),
                'total_quantity' => Quantity::format($others),
            ];
        }

        return $rows;
    }

    /**
     * @return Collection<int, Warehouse>
     */
    private function stockByWarehouse(): Collection
    {
        return Warehouse::query()
            ->select('warehouses.*')
            ->selectRaw('coalesce(sum(inventories.quantity), 0) as total_quantity')
            ->leftJoin('racks', 'racks.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('inventories', 'inventories.rack_id', '=', 'racks.id')
            ->groupBy('warehouses.id')
            ->orderByDesc('total_quantity')
            ->get();
    }

    /**
     * @return Builder<Handover>
     */
    private function takenHandovers(Request $request): Builder
    {
        $handovers = Handover::query()->where('status', Handover::STATUS_TAKEN);

        if (! $request->user()->can('handover.read_all')) {
            $handovers->where('custody_id', $request->user()->id);
        }

        return $handovers;
    }
}
