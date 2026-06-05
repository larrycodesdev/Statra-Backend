<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\LoginRequest;
use App\Http\Requests\CheckIn\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'username'   => $request->username,
            'name'       => $request->name,
            'first_name' => explode(' ', $request->name, 2)[0] ?? $request->name,
            'last_name'  => explode(' ', $request->name, 2)[1] ?? '',
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => 'checkin_user',
        ]);

        $token = $user->createToken('statra-checkin', ['checkin_user'])->plainTextToken;

        return response()->json([
            'success'  => true,
            'message'  => 'Registered successfully.',
            'data'     => [
                'token'    => $token,
                'pid'      => $user->pid,
                'name'     => $user->name,
                'username' => $user->username,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)
            ->where('role', 'checkin_user')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        $token = $user->createToken('statra-checkin', ['checkin_user'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'token'    => $token,
                'pid'      => $user->pid,
                'name'     => $user->name,
                'username' => $user->username,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }
}
