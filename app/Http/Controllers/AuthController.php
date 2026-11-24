<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Support\Lang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $permissions = $user->permissions;

        $resource = UserResource::make($user)->additional([
            'lang' => Lang::all(),
            'permissions' => $permissions,
        ]);

        return $resource->response()->setStatusCode(200);
    }
}
