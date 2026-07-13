<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    private const STAFF_ROLES = ['doctor', 'admin', 'staff', 'superadmin'];

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role'           => ['required', 'in:doctor,staff'],
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'password'       => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
            'phone'          => ['nullable', 'string', 'max:20'],
            'hospital_id'    => ['nullable', 'exists:hospitals,id'],
            // Doctor-specific
            'department'     => ['nullable', 'string', 'max:255'],
            'specialisation' => ['nullable', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:100', 'unique:doctors,license_number'],
        ]);

        $user = User::create([
            'first_name'      => $data['first_name'],
            'last_name'       => $data['last_name'],
            'name'            => $data['first_name'] . ' ' . $data['last_name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'role'            => $data['role'],
            'phone'           => $data['phone'] ?? null,
            'hospital_id'     => $data['hospital_id'] ?? null,
            'approval_status' => 'pending',
        ]);

        if ($data['role'] === 'doctor') {
            Doctor::create([
                'user_id'        => $user->id,
                'department'     => $data['department'] ?? null,
                'specialisation' => $data['specialisation'] ?? null,
                'license_number' => $data['license_number'] ?? null,
            ]);
        }

        $token = $user->createToken('web-dashboard', [$user->role])->plainTextToken;

        return ApiResponse::created([
            'token'           => $token,
            'user'            => $this->userResource($user),
            'approval_status' => 'pending',
        ], 'Registration successful. Account is pending admin approval.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])
            ->whereIn('role', self::STAFF_ROLES)
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        $token = $user->createToken('web-dashboard', [$user->role])->plainTextToken;

        return ApiResponse::success([
            'token'           => $token,
            'user'            => $this->userResource($user),
            'approval_status' => $user->approval_status,
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'id'        => $user->id,
            'uuid'      => $user->uuid,
            'fullName'  => $user->full_name,
            'initials'  => $user->initials,
            'email'     => $user->email,
            'phone'     => $user->phone,
            'avatar'    => $user->avatar,
            'role'      => $user->role,
            'hospital'  => $user->hospital ? [
                'id'   => $user->hospital->id,
                'name' => $user->hospital->name,
            ] : null,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

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

    public function acceptInvite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $user = User::where('email', $data['email'])
            ->whereNotNull('invite_token')
            ->first();

        if (
            !$user ||
            !hash_equals($user->invite_token, hash('sha256', $data['token'])) ||
            now()->isAfter($user->invite_expires_at)
        ) {
            return ApiResponse::error('Invalid or expired invite link.', 400);
        }

        $user->update([
            'password'          => Hash::make($data['password']),
            'invite_token'      => null,
            'invite_expires_at' => null,
        ]);

        $token = $user->createToken('web-dashboard', [$user->role])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Password set. You are now logged in.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    private function userResource(User $user): array
    {
        return [
            'id'       => $user->id,
            'uuid'     => $user->uuid,
            'fullName' => $user->full_name,
            'initials' => $user->initials,
            'email'    => $user->email,
            'phone'    => $user->phone,
            'role'     => $user->role,
        ];
    }
}
