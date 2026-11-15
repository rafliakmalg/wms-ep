<?php

namespace App\Http\Controllers;

use App\Http\Requests\Warehouse\IndexWarehouseRequest;
use App\Http\Requests\Warehouse\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends Controller
{
    public function index(IndexWarehouseRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Warehouse::query()->with('branch');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('code', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $warehouses = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return WarehouseResource::collection($warehouses);
    }

    public function store(WarehouseRequest $request): WarehouseResource
    {
        $param = $request->validated();

        $warehouse = Warehouse::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $warehouse->load('branch');

        $message = __('index.created', ['resource' => __('index.tables.warehouses.name')]);

        return WarehouseResource::make($warehouse)->additional(['message' => $message]);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        $warehouse->load('branch');

        return WarehouseResource::make($warehouse);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $param = $request->validated();

        $warehouse->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $warehouse->load('branch');

        $message = __('index.updated', ['resource' => __('index.tables.warehouses.name')]);

        return WarehouseResource::make($warehouse)->additional(['message' => $message]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.tables.warehouses.name')])]);
    }
}
