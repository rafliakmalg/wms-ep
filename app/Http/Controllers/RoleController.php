<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

        return RoleResource::make($role);
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

        return RoleResource::make($role);
    }

    public function destroy(Role $role): Response
    {
        $role->delete();

        return response()->noContent();
    }
}
