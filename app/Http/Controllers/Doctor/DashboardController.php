<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Services\QueryScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly QueryScope $scope) {}

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $patientQuery = $this->scope->patients($request);
        $alertQuery   = $this->scope->alerts($request);

        $totalPatients = (clone $patientQuery)->count();

        $todayStart = now()->startOfDay();
        $yesterday  = now()->subDay();

        // Alert level counts (L1=level 1 critical, L2=level 2 warning, L3=no active alert)
        $l1Count = (clone $alertQuery)->where('level', 1)->where('status', 'pending')->count();
        $l2Count = (clone $alertQuery)->where('level', 2)->where('status', 'pending')->count();

        // Patients with no pending alert = L3 (stable)
        $patientsWithPendingAlert = (clone $patientQuery)
            ->whereHas('alerts', fn ($q) => $q->where('status', 'pending'))
            ->count();
        $l3Count = max(0, $totalPatients - $patientsWithPendingAlert);

        $resolvedToday = (clone $alertQuery)
            ->where('status', 'resolved')
            ->where('resolved_at', '>=', $todayStart)
            ->count();

        // Yesterday's total patients (approximation via patient count — static for now)
        $pendingTotal    = (clone $alertQuery)->where('status', 'pending')->count();

        return ApiResponse::success([
            'totalPatients'   => $totalPatients,
            'alertLevelCounts' => [
                'L1' => $l1Count,
                'L2' => $l2Count,
                'L3' => $l3Count,
            ],
            'pendingAlerts'   => $pendingTotal,
            'resolvedToday'   => $resolvedToday,
            'readmissionRate' => null, // placeholder — requires discharge tracking
            'lastUpdatedAt'   => now()->toISOString(),
        ]);
    }

    public function alertFeed(Request $request): JsonResponse
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

        $alerts->through(function (Alert $alert) {
            return [
                'id'          => $alert->id,
                'level'       => $this->mapAlertLevel($alert->level),
                'rawLevel'    => $alert->level,
                'type'        => $alert->type,
                'message'     => $alert->message,
                'status'      => $alert->status,
                'resolvedAt'  => $alert->resolved_at?->toISOString(),
                'createdAt'   => $alert->created_at->toISOString(),
                'patient'     => $alert->patient ? [
                    'id'        => $alert->patient->id,
                    'displayId' => $this->displayId($alert->patient->id),
                    'name'      => $alert->patient->user?->name,
                    'avatar'    => $alert->patient->user?->avatar,
                ] : null,
                'vital'       => $alert->vitalReading ? [
                    'type'       => $alert->vitalReading->type,
                    'value'      => $alert->vitalReading->value,
                    'unit'       => $alert->vitalReading->unit,
                    'recordedAt' => $alert->vitalReading->recorded_at,
                ] : null,
            ];
        });

        return ApiResponse::paginated($alerts);
    }

    public function weeklyAlertVolume(Request $request): JsonResponse
    {
        $days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date  = now()->subDays($i)->startOfDay();
            $end   = $date->copy()->endOfDay();
            $label = $date->format('D'); // Mon, Tue, …

            $count = $this->scope->alerts($request)
                ->whereBetween('created_at', [$date, $end])
                ->count();

            $days[] = [
                'date'  => $date->format('Y-m-d'),
                'label' => $label,
                'count' => $count,
            ];
        }

        return ApiResponse::success(['volume' => $days]);
    }

    private function mapAlertLevel(int $level): string
    {
        return match ($level) {
            1       => 'L1',
            2       => 'L2',
            default => 'L3',
        };
    }

    private function displayId(int $patientId): string
    {
        return 'SCW-' . str_pad($patientId, 3, '0', STR_PAD_LEFT);
    }
}
