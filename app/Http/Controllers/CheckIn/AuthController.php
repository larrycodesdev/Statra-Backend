<?php

namespace App\Http\Controllers\CheckIn;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIn\ForgotPasswordRequest;
use App\Http\Requests\CheckIn\LoginRequest;
use App\Http\Requests\CheckIn\RegisterRequest;
use App\Http\Requests\CheckIn\ResetPasswordRequest;
use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)
            ->where('role', 'checkin_user')
            ->first();

        // Always return the same response to avoid username enumeration
        if (!$user || !$user->email) {
            return response()->json([
                'success' => true,
                'message' => 'If that username has an email on file, a reset code has been sent.',
            ]);
        }

        $otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = now()->addMinutes(10);

        $user->update([
            'password_reset_otp'            => Hash::make($otp),
            'password_reset_otp_expires_at' => $expires,
        ]);

        Mail::to($user->email)->send(new OtpMail($otp, $user->name));

        return response()->json([
            'success' => true,
            'message' => 'If that username has an email on file, a reset code has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $user = User::where('username', $request->username)
            ->where('role', 'checkin_user')
            ->first();

        if (
            !$user
            || !$user->password_reset_otp
            || !$user->password_reset_otp_expires_at
            || now()->isAfter($user->password_reset_otp_expires_at)
            || !Hash::check($request->otp, $user->password_reset_otp)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset code.',
            ], 422);
        }

        $user->update([
            'password'                      => Hash::make($request->password),
            'password_reset_otp'            => null,
            'password_reset_otp_expires_at' => null,
        ]);

        // Revoke all existing tokens so stale sessions can't linger
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Please log in.',
        ]);
    }
}
