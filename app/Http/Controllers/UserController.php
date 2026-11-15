<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = User::query()->with('role');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('name', 'like', '%'.$param['search'].'%')
                    ->orWhere('username', 'like', '%'.$param['search'].'%')
                    ->orWhere('email', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

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

        $message = __('index.created', ['resource' => __('index.tables.users.name')]);

        return UserResource::make($user)->additional(['message' => $message]);
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

        $message = __('index.updated', ['resource' => __('index.tables.users.name')]);

        return UserResource::make($user)->additional(['message' => $message]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.tables.users.name')])]);
    }
}
