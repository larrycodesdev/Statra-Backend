<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Models\PatientSettings;
use App\Models\User;
use App\Services\AppleTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'unique:users,email'],
            'password'   => ['required', 'confirmed', PasswordRule::min(8)],
            'phone'      => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'first_name'        => $data['first_name'],
            'last_name'         => $data['last_name'],
            'name'              => "{$data['first_name']} {$data['last_name']}",
            'username'          => $this->generateUsername($data['first_name']),
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role'              => 'patient',
            'phone'             => $data['phone'] ?? null,
            'email_verified_at' => now(),
        ]);

        $patient = Patient::create(['user_id' => $user->id]);
        PatientSettings::create(['patient_id' => $patient->id]);

        $token = $user->createToken('website', ['patient'])->plainTextToken;

        return ApiResponse::created([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Account created successfully.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->where('role', 'patient')->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid email or password.', 401);
        }

        $token = $user->createToken('website', ['patient'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Login successful.');
    }

    public function social(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider'   => ['required', 'in:google,apple'],
            'token'      => ['required', 'string'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if ($data['provider'] === 'google') {
            // Google One Tap / Sign-In button returns an ID token (JWT credential)
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['token'],
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
            // Apple Sign In JS SDK returns an identity token (JWT)
            try {
                $payload = (new AppleTokenVerifier())->verify($data['token']);
            } catch (\Throwable) {
                return ApiResponse::error('Invalid Apple identity token.', 401);
            }

            $email = $payload['email'] ?? null;
            if (!$email) {
                return ApiResponse::error('This Apple account has no email address. Ensure "Share My Email" is selected.', 422);
            }

            // Apple only provides name on very first sign-in via the JS SDK
            $firstName = $data['first_name'] ?? '';
            $lastName  = $data['last_name'] ?? '';
            $avatar    = null;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'name'              => trim("$firstName $lastName") ?: $email,
                'username'          => $this->generateUsername($firstName ?: $email),
                'role'              => 'patient',
                'email_verified_at' => now(),
                'password'          => null,
                'avatar'            => $avatar,
            ]
        );

        if ($user->role !== 'patient') {
            return ApiResponse::error('This email is registered under a different account type.', 403);
        }

        if (!$user->patient) {
            $patient = Patient::create(['user_id' => $user->id]);
            PatientSettings::create(['patient_id' => $patient->id]);
        }

        $token = $user->createToken('website', ['patient'])->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 'Login successful.');
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
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar'     => $user->avatar,
        ];
    }

    private function generateUsername(string $base): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $base));
        $base = $base ?: 'user';
        $candidate = $base;
        $i = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $base . $i++;
        }
        return $candidate;
    }
}
