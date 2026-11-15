<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const DUMMY_HASH = '$2y$12$nOlg6dRS.n3cVo7BVPhFSONMnqso3keNqkDpeI4YR/iwrDLl1EhzK';

    public function login(LoginRequest $request): JsonResponse
    {
        $param = $request->validated();

        $user = User::where('email', $param['email'])->first();

        // utk mencegah timing attack, kita tetap melakukan pengecekan password meskipun user tidak ditemukan
        $passwordMatches = Hash::check($param['password'], $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $user->active || ! $passwordMatches) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }

        $token = $user->createToken('api')->plainTextToken;
        $user->load('role');

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => UserResource::make($user),
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user();
        $user->load('role');

        return UserResource::make($user);
    }
}
