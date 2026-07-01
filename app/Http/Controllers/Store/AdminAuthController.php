<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = StoreAdmin::where('email', $data['email'])->first();

        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $token = $admin->generateToken();

        return response()->json([
            'success' => true,
            'data'    => [
                'token' => $token,
                'admin' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $admin = $request->get('_store_admin');
        $admin->revokeToken();

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $admin = $request->get('_store_admin');

        if (!Hash::check($data['current_password'], $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $admin->update(['password' => $data['new_password']]);

        return response()->json(['success' => true, 'message' => 'Password updated successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->get('_store_admin');

        return response()->json([
            'success' => true,
            'data'    => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email],
        ]);
    }
}
