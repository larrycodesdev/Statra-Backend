<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Models\Patient;
use App\Models\VitalReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function healthTrends(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'type'       => ['nullable', 'in:heart_rate,spo2,temperature,blood_pressure,steps,sleep_state,hrv'],
            'range'      => ['nullable', 'in:7d,30d,90d'],
        ]);

        $doctorUserId = $request->user()->id;
        $from = match ($request->range) {
            '30d' => now()->subMonth(),
            '90d' => now()->subMonths(3),
            default => now()->subWeek(),
        };

        $query = VitalReading::whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $doctorUserId))
            ->where('recorded_at', '>=', $from)
            ->select('type', 'value', 'recorded_at');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $trends = $query->orderBy('recorded_at')->get();

        return ApiResponse::success($trends);
    }

    public function alertsAnalytics(Request $request): JsonResponse
    {
        $doctorUserId = $request->user()->id;

        $base = Alert::whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $doctorUserId));

        $analytics = [
            'total'          => (clone $base)->count(),
            'critical'       => (clone $base)->where('level', 1)->count(),
            'warnings'       => (clone $base)->where('level', 2)->count(),
            'pending'        => (clone $base)->where('status', 'pending')->count(),
            'resolved'       => (clone $base)->where('status', 'resolved')->count(),
            'last_7_days'    => (clone $base)->where('created_at', '>=', now()->subWeek())->count(),
            'by_type'        => (clone $base)->selectRaw('type, COUNT(*) as count')->groupBy('type')->pluck('count', 'type'),
        ];

        return ApiResponse::success($analytics);
    }

    public function export(Request $request): Response|JsonResponse
    {
        $request->validate([
            'format'     => ['nullable', 'in:csv'],
            'patient_id' => ['nullable', 'exists:patients,id'],
            'range'      => ['nullable', 'in:7d,30d,90d'],
        ]);

        $doctorUserId = $request->user()->id;
        $from = match ($request->range) {
            '30d' => now()->subMonth(),
            '90d' => now()->subMonths(3),
            default => now()->subWeek(),
        };

        $query = VitalReading::with('patient.user:id,name')
            ->whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $doctorUserId))
            ->where('recorded_at', '>=', $from)
            ->orderBy('recorded_at');

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        $readings = $query->get();

        $csv   = "patient_name,type,value,unit,recorded_at\n";
        foreach ($readings as $r) {
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
