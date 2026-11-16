<?php

namespace App\Http\Controllers;

use App\Http\Requests\Inventory\IndexInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Support\Filter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function index(IndexInventoryRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Inventory::query()->with(['product.baseUnit', 'rack.warehouse.branch']);

        if ($param['search']) {
            $query->whereHas('product', function ($query) use ($param) {
                $query->where('sku', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $inventories = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return InventoryResource::collection($inventories);
    }
}
