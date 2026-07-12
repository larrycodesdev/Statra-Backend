<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Models\VitalReading;
use App\Services\QueryScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function __construct(private readonly QueryScope $scope) {}

    public function summary(Request $request): JsonResponse
    {
        $patientQuery = $this->scope->patients($request);
        $alertQuery   = $this->scope->alerts($request);

        return ApiResponse::success([
            'totalPatients'    => (clone $patientQuery)->count(),
            'totalAlerts'      => (clone $alertQuery)->count(),
            'criticalAlerts'   => (clone $alertQuery)->where('level', 1)->count(),
            'warningAlerts'    => (clone $alertQuery)->where('level', 2)->count(),
            'resolvedAlerts'   => (clone $alertQuery)->where('status', 'resolved')->count(),
            'last7DaysAlerts'  => (clone $alertQuery)->where('created_at', '>=', now()->subWeek())->count(),
        ]);
    }

    public function weeklyAlertTrend(Request $request): JsonResponse
    {
        $trend = [];

        for ($i = 6; $i >= 0; $i--) {
            $date  = now()->subDays($i)->startOfDay();
            $end   = $date->copy()->endOfDay();

            $trend[] = [
                'date'  => $date->format('Y-m-d'),
                'label' => $date->format('D'),
                'count' => $this->scope->alerts($request)
                    ->whereBetween('created_at', [$date, $end])
                    ->count(),
            ];
        }

        return ApiResponse::success(['trend' => $trend]);
    }

    public function alertTypeBreakdown(Request $request): JsonResponse
    {
        $breakdown = $this->scope->alerts($request)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return ApiResponse::success(['breakdown' => $breakdown]);
    }

    public function healthTrends(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'type'       => ['nullable', 'in:heart_rate,spo2,temperature,blood_pressure,steps,sleep_state,hrv'],
            'range'      => ['nullable', 'in:7d,30d,90d'],
        ]);

        $from = match ($request->range) {
            '30d' => now()->subMonth(),
            '90d' => now()->subMonths(3),
            default => now()->subWeek(),
        };

        $visibleIds = $this->scope->patients($request)->pluck('id');

        $query = VitalReading::whereIn('patient_id', $visibleIds)
            ->where('recorded_at', '>=', $from)
            ->select('type', 'value', 'recorded_at');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return ApiResponse::success($query->orderBy('recorded_at')->get());
    }

    public function alertsAnalytics(Request $request): JsonResponse
    {
        $base = $this->scope->alerts($request);

        return ApiResponse::success([
            'total'       => (clone $base)->count(),
            'critical'    => (clone $base)->where('level', 1)->count(),
            'warnings'    => (clone $base)->where('level', 2)->count(),
            'pending'     => (clone $base)->where('status', 'pending')->count(),
            'resolved'    => (clone $base)->where('status', 'resolved')->count(),
            'last_7_days' => (clone $base)->where('created_at', '>=', now()->subWeek())->count(),
            'by_type'     => (clone $base)->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type'),
        ]);
    }

    public function export(Request $request): Response|JsonResponse
    {
        $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'range'      => ['nullable', 'in:7d,30d,90d'],
        ]);

        $from = match ($request->range) {
            '30d' => now()->subMonth(),
            '90d' => now()->subMonths(3),
            default => now()->subWeek(),
        };

        $visibleIds = $this->scope->patients($request)->pluck('id');

        $query = VitalReading::with('patient.user:id,name')
            ->whereIn('patient_id', $visibleIds)
            ->where('recorded_at', '>=', $from)
            ->orderBy('recorded_at');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        $csv = "patient_name,type,value,unit,recorded_at\n";
        foreach ($query->get() as $r) {
            $name  = $r->patient->user->name ?? '';
            $value = is_array($r->value) ? json_encode($r->value) : $r->value;
            $csv  .= "\"{$name}\",\"{$r->type}\",\"{$value}\",\"{$r->unit}\",\"{$r->recorded_at}\"\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="vitals-export.csv"',
        ]);
    }
}
