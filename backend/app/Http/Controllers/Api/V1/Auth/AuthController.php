<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\TokenLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /** Login Admin Web (Sanctum SPA, cookie sesi). Panggil GET /sanctum/csrf-cookie terlebih dahulu. */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->loginWithSession(
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
            $request,
        );

        return response()->json([
            'message' => 'Berhasil masuk.',
            'data' => new UserResource($user),
        ]);
    }

    /** Login aplikasi mobile (Flutter): mengembalikan token Bearer. */
    public function token(TokenLoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->issueToken(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        return response()->json([
            'message' => 'Berhasil masuk.',
            'token' => $token,
            'token_type' => 'Bearer',
            'data' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return response()->json(['message' => __('auth.logged_out')]);
    }
}
