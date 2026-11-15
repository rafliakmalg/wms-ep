<?php

namespace App\Http\Controllers;

use App\Http\Requests\Branch\BranchRequest;
use App\Http\Requests\Branch\IndexBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(IndexBranchRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Branch::query();

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('code', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $branches = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return BranchResource::collection($branches);
    }

    public function store(BranchRequest $request): BranchResource
    {
        $param = $request->validated();

        $branch = Branch::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.created', ['resource' => __('index.tables.branches.name')]);

        return BranchResource::make($branch)->additional(['message' => $message]);
    }

    public function show(Branch $branch): BranchResource
    {
        return BranchResource::make($branch);
    }

    public function update(BranchRequest $request, Branch $branch): BranchResource
    {
        $param = $request->validated();

        $branch->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.updated', ['resource' => __('index.tables.branches.name')]);

        return BranchResource::make($branch)->additional(['message' => $message]);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $branch->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.tables.branches.name')])]);
    }
}
