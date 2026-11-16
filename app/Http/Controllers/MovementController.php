<?php

namespace App\Http\Controllers;

use App\Http\Requests\Movement\IndexMovementRequest;
use App\Http\Requests\Movement\MovementRequest;
use App\Http\Resources\MovementResource;
use App\Models\Inventory;
use App\Models\Movement;
use App\Models\Rack;
use App\Support\Filter;
use App\Support\Quantity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MovementController extends Controller
{
    public function index(IndexMovementRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Movement::query()->with(['adjustment', 'handover', 'product', 'rackFrom.warehouse', 'rackTo.warehouse']);

        if ($param['search']) {
            $query->whereHas('product', function ($query) use ($param) {
                $query->where('sku', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $movements = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return MovementResource::collection($movements);
    }

    public function store(MovementRequest $request): MovementResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $quantity = Quantity::format($param['quantity']);
        $return = new MovementResource(null);

        DB::transaction(function () use ($param, $userId, $quantity, &$return) {
            if (isset($param['rack_to_id'])) {
                Rack::lockAndCheckActive(['rack_to_id' => $param['rack_to_id']], 'index.movement.rack_to_id');
            }

            $changes = [];

            if (isset($param['rack_from_id'])) {
                $changes[] = [
                    'path' => 'quantity',
                    'product_id' => $param['product_id'],
                    'quantity' => '-'.$quantity,
                    'rack_id' => $param['rack_from_id'],
                ];
            }

            if (isset($param['rack_to_id'])) {
                $changes[] = [
                    'path' => 'quantity',
                    'product_id' => $param['product_id'],
                    'quantity' => $quantity,
                    'rack_id' => $param['rack_to_id'],
                ];
            }

            Inventory::applyChanges($changes, $userId);

            $movement = Movement::create([
                ...$param,
                'quantity' => $quantity,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $movement->load(['adjustment', 'handover', 'product', 'rackFrom.warehouse', 'rackTo.warehouse']);

            $message = __('index.created', ['resource' => __('index.movement')]);

            $return = MovementResource::make($movement)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }
}
