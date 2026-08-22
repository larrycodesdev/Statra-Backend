<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // PATCH /admin/users/{id}/tester
    public function toggleTester(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_tester' => ['required', 'boolean'],
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        $user->update(['is_tester' => $data['is_tester']]);

        return response()->json([
            'success' => true,
            'message' => $data['is_tester']
                ? "{$user->full_name} is now a tester — full access granted."
                : "{$user->full_name} tester access removed.",
            'data'    => ['user_id' => $user->id, 'is_tester' => $user->is_tester],
        ]);
    }
}
