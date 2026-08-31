<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Mail\OtpMail;
use App\Models\Patient;
use App\Models\PatientSettings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Services\AppleTokenVerifier;
use App\Services\FirebaseTokenVerifier;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'username'   => ['nullable', 'string', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'confirmed', PasswordRule::min(8)],
            'phone'      => ['nullable', 'string', 'max:20'],
        ]);

        $firstName = $data['first_name'];
        $lastName  = $data['last_name'];
        $username  = $data['username'] ?? $this->generateUsername($firstName);

        $user = User::create([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'name'       => "$firstName $lastName",
            'username'   => $username,
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'role'       => 'patient',
            'phone'      => $data['phone'] ?? null,
        ]);

        $patient = Patient::create(['user_id' => $user->id]);
        PatientSettings::create(['patient_id' => $patient->id]);

        $token = $user->createToken('mobile-app', ['patient'])->plainTextToken;

        return ApiResponse::created([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Registration successful.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],  // email OR username
            'password'   => ['required', 'string'],
        ]);

        $isEmail = filter_var($data['identifier'], FILTER_VALIDATE_EMAIL);
        $field   = $isEmail ? 'email' : 'username';

        $user = User::where($field, $data['identifier'])->where('role', 'patient')->first();

        if (!$user) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        // Account was created via Google/Apple — no password set
        if (is_null($user->password)) {
            return ApiResponse::error('This account uses social sign-in (Google or Apple). Please sign in with Google or Apple instead.', 401);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

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
            'provider'   => ['required', 'in:firebase,google,google_web,apple,facebook'],
            'token'      => ['required', 'string'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'string', 'max:100'],
            'fcm_token'  => ['sometimes', 'nullable', 'string'],
        ]);

        // Firebase ID token path — mobile app signed in via Firebase Auth SDK
        if ($data['provider'] === 'firebase') {
            try {
                $payload = (new FirebaseTokenVerifier())->verify($data['token']);
            } catch (\Throwable) {
                return ApiResponse::error('Invalid Firebase token.', 401);
            }

            $email = $payload['email'] ?? null;
            if (!$email) {
                return ApiResponse::error('This Google account has no email address.', 422);
            }

            $nameParts = explode(' ', $payload['name'] ?? '', 2);
            $firstName = $nameParts[0] ?? '';
            $lastName  = $nameParts[1] ?? '';
            $avatar    = $payload['picture'] ?? null;

        } elseif ($data['provider'] === 'google') {
            // Mobile Google Sign-In SDK returns either an OAuth access token (opaque)
            // or a Google ID token (JWT with 3 dot-separated parts). Handle both.
            $token  = $data['token'];
            $isJwt  = substr_count($token, '.') === 2;

            if ($isJwt) {
                // ID token path — verify via Google tokeninfo
                $response = \Illuminate\Support\Facades\Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $token,
                ]);
                if (!$response->successful() || !$response->json('email')) {
                    return ApiResponse::error('Invalid Google token.', 401);
                }
                $info      = $response->json();
                $email     = $info['email'];
                $firstName = $info['given_name'] ?? ($data['first_name'] ?? '');
                $lastName  = $info['family_name'] ?? ($data['last_name'] ?? '');
                $avatar    = $info['picture'] ?? null;
            } else {
                // OAuth access token path — verify via Socialite
                try {
                    $socialUser = Socialite::driver('google')->stateless()->userFromToken($token);
                } catch (\Throwable) {
                    return ApiResponse::error('Invalid Google token.', 401);
                }
                $email     = $socialUser->getEmail();
                $nameParts = explode(' ', $socialUser->getName() ?? '', 2);
                $firstName = $nameParts[0] ?? '';
                $lastName  = $nameParts[1] ?? '';
                $avatar    = $socialUser->getAvatar();
            }

        } elseif ($data['provider'] === 'google_web') {
            // Google One Tap / Sign-In button on website returns an ID token (JWT credential)
            // Verified via Google's tokeninfo endpoint — different from Firebase (mobile) flow
            $response = \Illuminate\Support\Facades\Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['token'],
            ]);

            if (!$response->successful() || !$response->json('email')) {
                return ApiResponse::error('Invalid Google token.', 401);
            }

            $info = $response->json();

            // Validate the token was issued for our web client, not some other Google app
            $expectedAud = config('services.google.web_client_id');
            if ($expectedAud && ($info['aud'] ?? '') !== $expectedAud) {
                return ApiResponse::error('Invalid Google token.', 401);
            }

            $email     = $info['email'];
            $firstName = $info['given_name'] ?? ($data['first_name'] ?? '');
            $lastName  = $info['family_name'] ?? ($data['last_name'] ?? '');
            $avatar    = $info['picture'] ?? null;

        } elseif ($data['provider'] === 'apple') {
            // Apple identity token path — name is NOT in the token (only provided on first sign-in)
            // Mobile dev must send first_name/last_name from sign_in_with_apple package on first login
            try {
                $payload = (new AppleTokenVerifier())->verify($data['token']);
            } catch (\Throwable) {
                return ApiResponse::error('Invalid Apple identity token.', 401);
            }

            $email = $payload['email'] ?? null;
            if (!$email) {
                return ApiResponse::error('This Apple account has no email address. Ensure "Share My Email" is selected.', 422);
            }

            $firstName = $data['first_name'] ?? '';
            $lastName  = $data['last_name'] ?? '';
            $avatar    = null;

        } else {
            // Facebook — OAuth access token via Socialite
            try {
                /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
                $socialUser = Socialite::driver($data['provider'])->stateless()->userFromToken($data['token']);
            } catch (\Throwable) {
                return ApiResponse::error('Invalid social token.', 401);
            }

            $email     = $socialUser->getEmail();
            $nameParts = explode(' ', $socialUser->getName() ?? '', 2);
            $firstName = $nameParts[0] ?? '';
            $lastName  = $nameParts[1] ?? '';
            $avatar    = $socialUser->getAvatar();
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'name'              => trim("$firstName $lastName") ?: $email,
                'username'          => $this->generateUsername($firstName),
                'role'              => 'patient',
                'email_verified_at' => now(),
                'password'          => null,
                'avatar'            => $avatar,
            ]
        );

        if ($user->role !== 'patient') {
            return ApiResponse::error('This email is registered as a doctor account.', 403);
        }

        if (!$user->patient) {
            $patient = Patient::create(['user_id' => $user->id]);
            PatientSettings::create(['patient_id' => $patient->id]);
        }

        if ($request->filled('fcm_token')) {
            $user->update(['fcm_token' => $request->fcm_token]);
        }

        $token = $user->createToken('mobile-app', ['patient'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Login successful.');
    }

    // Step 1: Send OTP to email
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->where('role', 'patient')->first();

        if ($user) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("otp:{$request->email}", $otp, now()->addMinutes(10));
            Mail::to($user->email)->send(new OtpMail($otp, $user->first_name ?? $user->name));
        }

        // Never reveal whether email exists (prevents enumeration)
        return ApiResponse::success(null, 'If that email is registered, a 6-digit code has been sent.');
    }

    // Step 2: Verify OTP, receive a reset_token
    public function verifyOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $cached = Cache::get("otp:{$data['email']}");

        if (!$cached || $cached !== $data['otp']) {
            return ApiResponse::error('Invalid or expired OTP.', 400);
        }

        Cache::forget("otp:{$data['email']}");

        $resetToken = Str::random(64);
        Cache::put("reset_token:{$resetToken}", $data['email'], now()->addMinutes(15));

        return ApiResponse::success(['reset_token' => $resetToken], 'OTP verified. Proceed to reset your password.');
    }

    // Step 3: Reset password using reset_token from step 2
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reset_token'           => ['required', 'string'],
            'password'              => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $email = Cache::get("reset_token:{$data['reset_token']}");

        if (!$email) {
            return ApiResponse::error('Invalid or expired reset token. Please restart the process.', 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return ApiResponse::error('Account not found.', 404);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        $user->tokens()->delete();
        Cache::forget("reset_token:{$data['reset_token']}");

        return ApiResponse::success(null, 'Password reset successfully. Please log in.');
    }

    // Change password while logged in (from Settings screen)
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if (!Hash::check($data['current_password'], $request->user()->password)) {
            return ApiResponse::error('Current password is incorrect.', 400);
        }

        $request->user()->update(['password' => Hash::make($data['password'])]);

        // Revoke all other tokens so other devices get logged out
        $currentToken = $request->user()->currentAccessToken()->id;
        $request->user()->tokens()->where('id', '!=', $currentToken)->delete();

        return ApiResponse::success(null, 'Password updated successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    private function userResource(User $user): array
    {
        return [
            'id'         => $user->id,
            'uuid'       => $user->uuid,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'name'       => $user->full_name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'avatar'     => $user->avatar,
            'role'       => $user->role,
        ];
    }

    private function generateUsername(string $firstName): string
    {
        $base = Str::slug(strtolower($firstName), '');
        $candidate = $base . random_int(100, 9999);

        // Retry until unique (rare edge case)
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . random_int(100, 9999);
        }

        return $candidate;
    }
}
