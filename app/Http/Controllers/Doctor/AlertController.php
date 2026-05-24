<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'  => ['nullable', 'in:pending,acknowledged,resolved'],
            'level'   => ['nullable', 'in:1,2'],
        ]);

        $doctorUserId = $request->user()->id;

        $query = Alert::whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $doctorUserId))
            ->with([
                'patient.user:id,name,avatar',
                'vitalReading:id,type,value,unit,recorded_at',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        return ApiResponse::paginated($query->paginate(20));
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $alert = $this->resolveAlert($request, $id);

        if (!$alert) {
            return ApiResponse::notFound('Alert not found.');
        }

        $alert->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);

        return ApiResponse::success(null, 'Alert resolved.');
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $alert = $this->resolveAlert($request, $id);

        if (!$alert) {
            return ApiResponse::notFound('Alert not found.');
        }

        $request->validate([
            'doctor_id' => ['required', 'exists:users,id'],
        ]);

        $alert->update([
            'assigned_to' => $request->doctor_id,
            'status'      => 'acknowledged',
        ]);

        return ApiResponse::success(null, 'Alert assigned.');
    }

    private function resolveAlert(Request $request, int $id): ?Alert
    {
        return Alert::whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $request->user()->id))
            ->find($id);
    }
}
