<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\LoginData;
use App\Data\RegisterData;
use App\Data\UserData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function register(RegisterData $data, Request $request): JsonResponse
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'locale' => $data->locale ?? config('app.fallback_locale'),
            ]);

            $user->assignRole('learner');

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['data' => UserData::fromModel($user)], 201);
    }

    public function login(LoginData $data, Request $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt(
            ['email' => $data->email, 'password' => $data->password],
            $data->remember,
        )) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return response()->json([
            'data' => UserData::fromModel($request->user()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserData::fromModel($request->user()),
        ]);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
