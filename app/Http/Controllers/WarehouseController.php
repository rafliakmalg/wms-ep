<?php

namespace App\Http\Controllers;

use App\Http\Requests\Warehouse\IndexWarehouseRequest;
use App\Http\Requests\Warehouse\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

        if ($param['branch_id']) {
            $query->where('branch_id', $param['branch_id']);
        }

        if ($param['active'] !== null) {
            $query->where('active', $param['active']);
        }

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

        return WarehouseResource::make($warehouse);
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

        return WarehouseResource::make($warehouse);
    }

    public function destroy(Warehouse $warehouse): Response
    {
        $warehouse->delete();

        return response()->noContent();
    }
}
