<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rack\IndexRackRequest;
use App\Http\Requests\Rack\RackRequest;
use App\Http\Resources\RackResource;
use App\Models\Rack;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RackController extends Controller
{
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

    public function show(Rack $rack): RackResource
    {
        $rack->load('warehouse');

        return RackResource::make($rack);
    }

    public function update(RackRequest $request, Rack $rack): RackResource
    {
        $param = $request->validated();

        $rack->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $rack->load('warehouse');

        $message = __('index.updated', ['resource' => __('index.rack')]);

        return RackResource::make($rack)->additional(['message' => $message]);
    }

    public function destroy(Rack $rack): JsonResponse
    {
        $rack->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.rack')])]);
    }
}
