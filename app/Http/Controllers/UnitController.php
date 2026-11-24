<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unit\IndexUnitRequest;
use App\Http\Requests\Unit\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class UnitController extends Controller
{
    #[Authorize('unit.read')]
    public function index(IndexUnitRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Unit::query();

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('code', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $units = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return UnitResource::collection($units);
    }

    #[Authorize('unit.create')]
    public function store(UnitRequest $request): UnitResource
    {
        $param = $request->validated();

        $unit = Unit::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.created', ['resource' => __('index.unit')]);

        return UnitResource::make($unit)->additional(['message' => $message]);
    }

    #[Authorize('unit.read')]
    public function show(Unit $unit): UnitResource
    {
        return UnitResource::make($unit);
    }

    #[Authorize('unit.update')]
    public function update(UnitRequest $request, Unit $unit): UnitResource
    {
        $param = $request->validated();

        $unit->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.updated', ['resource' => __('index.unit')]);

        return UnitResource::make($unit)->additional(['message' => $message]);
    }

    #[Authorize('unit.delete')]
    public function destroy(Unit $unit): JsonResponse
    {
        $unit->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.unit')])]);
    }
}
