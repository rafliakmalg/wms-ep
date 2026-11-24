<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Filter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = User::query();

        if ($param['search']) {
            $query->where('name', 'like', '%'.$param['search'].'%');
        }

        Filter::apply($query, $param['filters']);

        $users = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return UserResource::collection($users);
    }
}
