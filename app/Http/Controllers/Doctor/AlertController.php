<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Services\QueryScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private readonly QueryScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:pending,acknowledged,resolved'],
            'level'  => ['nullable', 'in:1,2'],
        ]);

        $query = $this->scope->alerts($request)
            ->with([
                'patient.user:id,name,first_name,last_name,avatar',
                'vitalReading:id,type,value,unit,recorded_at',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        $alerts = $query->paginate(20);

        $alerts->through(fn ($a) => $this->formatAlert($a));

        return ApiResponse::paginated($alerts);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $alert = $this->resolveAlert($request, $id);

        if (!$alert) {
            return ApiResponse::notFound('Alert not found.');
        }

        $alert->load([
            'patient.user:id,name,first_name,last_name,avatar',
            'vitalReading:id,type,value,unit,recorded_at',
        ]);

        return ApiResponse::success($this->formatAlert($alert));
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

        // Staff cannot assign alerts
        if ($request->user()->role === 'staff') {
            return ApiResponse::error('Access denied.', 403);
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
        return $this->scope->alerts($request)->find($id);
    }

    private function formatAlert(Alert $alert): array
    {
        return [
            'id'         => $alert->id,
            'level'      => $this->mapLevel($alert->level),
            'rawLevel'   => $alert->level,
            'type'       => $alert->type,
            'message'    => $alert->message,
            'status'     => $alert->status,
            'resolvedAt' => $alert->resolved_at?->toISOString(),
            'createdAt'  => $alert->created_at->toISOString(),
            'patient'    => $alert->patient ? [
                'id'        => $alert->patient->id,
                'displayId' => 'SCW-' . str_pad($alert->patient->id, 3, '0', STR_PAD_LEFT),
                'name'      => $alert->patient->user?->name,
                'avatar'    => $alert->patient->user?->avatar,
            ] : null,
            'vital'      => $alert->vitalReading ? [
                'type'       => $alert->vitalReading->type,
                'value'      => $alert->vitalReading->value,
                'unit'       => $alert->vitalReading->unit,
                'recordedAt' => $alert->vitalReading->recorded_at,
            ] : null,
        ];
    }

    private function mapLevel(int $level): string
    {
        return match ($level) {
            1 => 'L1',
            2 => 'L2',
            default => 'L3',
        };
    }
}
