<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = User::query()->with('role');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('name', 'like', '%'.$param['search'].'%')
                    ->orWhere('email', 'like', '%'.$param['search'].'%');
            });
        }

        if ($param['role_id']) {
            $query->where('role_id', $param['role_id']);
        }

        if ($param['active'] !== null) {
            $query->where('active', $param['active']);
        }

        $users = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(UserRequest $request): UserResource
    {
        $param = $request->validated();

        $user = User::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $user->load('role');

        return UserResource::make($user);
    }

    public function show(User $user): UserResource
    {
        $user->load('role');

        return UserResource::make($user);
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $param = $request->validated();

        $user->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $user->load('role');

        return UserResource::make($user);
    }

    public function destroy(User $user): Response
    {
        $user->delete();

        return response()->noContent();
    }
}
