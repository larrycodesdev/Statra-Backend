<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admin and superadmin can list staff
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return ApiResponse::error('Access denied.', 403);
        }

        $request->validate([
            'role'            => ['nullable', 'in:doctor,staff,admin'],
            'approval_status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $query = User::whereIn('role', ['doctor', 'staff', 'admin'])
            ->orderBy('name');

        // Admin scoped to their hospital
        if ($user->role === 'admin') {
            $query->where('hospital_id', $user->hospital_id);
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        $staff = $query->get(['id', 'name', 'first_name', 'last_name', 'email', 'phone',
                              'role', 'hospital_id', 'approval_status', 'created_at']);

        return ApiResponse::success($staff->map(fn ($s) => [
            'id'             => $s->id,
            'name'           => trim("{$s->first_name} {$s->last_name}") ?: $s->name,
            'email'          => $s->email,
            'phone'          => $s->phone,
            'role'           => $s->role,
            'approvalStatus' => $s->approval_status,
            'registeredAt'   => $s->created_at->toISOString(),
        ]));
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return ApiResponse::error('Access denied.', 403);
        }

        $request->validate([
            'action' => ['required', 'in:approve,reject'],
        ]);

        $staffUser = User::whereIn('role', ['doctor', 'staff'])
            ->find($id);

        if (!$staffUser) {
            return ApiResponse::notFound('Staff member not found.');
        }

        // Admin can only approve within their hospital
        if ($user->role === 'admin' && (int) $staffUser->hospital_id !== (int) $user->hospital_id) {
            return ApiResponse::error('Access denied.', 403);
        }

        $newStatus = $request->action === 'approve' ? 'approved' : 'rejected';

        $staffUser->update(['approval_status' => $newStatus]);

        return ApiResponse::success([
            'id'             => $staffUser->id,
            'approvalStatus' => $newStatus,
        ], 'Staff status updated.');
    }
}
