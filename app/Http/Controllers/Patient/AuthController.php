<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
            'phone'    => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'patient',
            'phone'    => $data['phone'] ?? null,
        ]);

        Patient::create(['user_id' => $user->id]);

        $token = $user->createToken('mobile-app', ['patient'])->plainTextToken;

        return ApiResponse::created([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Registration successful.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->where('role', 'patient')->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        // Revoke all previous mobile-app tokens for this device if fcm_token provided
        if ($request->filled('fcm_token')) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        $token = $user->createToken('mobile-app', ['patient'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Login successful.');
    }

    public function social(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:google,apple,facebook'],
            'token'    => ['required', 'string'],
        ]);

        try {
            $socialUser = Socialite::driver($data['provider'])->stateless()->userFromToken($data['token']);
        } catch (\Throwable $e) {
            return ApiResponse::error('Invalid social token.', 401);
        }

        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name'              => $socialUser->getName() ?? $socialUser->getEmail(),
                'role'              => 'patient',
                'email_verified_at' => now(),
                'password'          => null,
            ]
        );

        if ($user->role !== 'patient') {
            return ApiResponse::error('This email is registered as a doctor account.', 403);
        }

        if (!$user->patient) {
            Patient::create(['user_id' => $user->id]);
        }

        $token = $user->createToken('mobile-app', ['patient'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Social login successful.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Always return success to avoid email enumeration
        return ApiResponse::success(null, 'If that email exists, a reset link has been sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->update(['password' => Hash::make($password)]);
            $user->tokens()->delete();
        });

        if ($status !== Password::PasswordReset) {
            return ApiResponse::error('Invalid or expired reset token.', 400);
        }

        return ApiResponse::success(null, 'Password reset successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    private function userResource(User $user): array
    {
        return [
            'id'    => $user->id,
            'uuid'  => $user->uuid,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role'  => $user->role,
        ];
    }
}
