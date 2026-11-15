<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoleController extends Controller
{
    public function index(IndexRoleRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Role::query();

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('label', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $roles = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return RoleResource::collection($roles);
    }

    public function store(RoleRequest $request): RoleResource
    {
        $param = $request->validated();

        $role = Role::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.created', ['resource' => __('index.role')]);

        return RoleResource::make($role)->additional(['message' => $message]);
    }

    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role);
    }

    public function update(RoleRequest $request, Role $role): RoleResource
    {
        $param = $request->validated();

        $role->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.updated', ['resource' => __('index.role')]);

        return RoleResource::make($role)->additional(['message' => $message]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.role')])]);
    }
}
