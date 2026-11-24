<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rack\IndexRackRequest;
use App\Http\Requests\Rack\RackRequest;
use App\Http\Resources\RackResource;
use App\Models\Inventory;
use App\Models\Rack;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RackController extends Controller
{
    #[Authorize('rack.delete')]
    public function destroy(Rack $rack): JsonResponse
    {
        $rack->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.rack')])]);
    }

    #[Authorize('rack.read')]
    public function index(IndexRackRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Rack::query()->with('warehouse');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('code', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $racks = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return RackResource::collection($racks);
    }

    #[Authorize('rack.read')]
    public function show(Rack $rack): RackResource
    {
        $rack->load('warehouse');

        return RackResource::make($rack);
    }

    #[Authorize('rack.create')]
    public function store(RackRequest $request): RackResource
    {
        $param = $request->validated();

        $rack = Rack::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $rack->load('warehouse');

        $message = __('index.created', ['resource' => __('index.rack')]);

        return RackResource::make($rack)->additional(['message' => $message]);
    }

    #[Authorize('rack.update')]
    public function update(RackRequest $request, Rack $rack): RackResource
    {
        $param = $request->validated();
        $return = new RackResource(null);

        DB::transaction(function () use ($param, $rack, $request, &$return) {
            $lockedRack = Rack::query()
                ->where('id', $rack->id)
                ->lockForUpdate()
                ->firstOrFail();

            $deactivating = ! $request->boolean('active', $lockedRack->active);

            $hasIncoming = Inventory::query()
                ->where('rack_id', $lockedRack->id)
                ->where('quantity_incoming', '>', 0)
                ->exists();

            if ($deactivating && $hasIncoming) {
                throw ValidationException::withMessages(['active' => __('index.rack.has_incoming')]);
            }

            $lockedRack->update([
                ...$param,
                'updated_by_id' => $request->user()->id,
            ]);

            $lockedRack->load('warehouse');

            $message = __('index.updated', ['resource' => __('index.rack')]);

            $return = RackResource::make($lockedRack)->additional(['message' => $message]);
        });

        return $return;
    }
}
