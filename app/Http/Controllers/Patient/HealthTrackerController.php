<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthTrackerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        $limit   = 10;

        // Latest heart rate readings — no date filter, whatever is newest
        $heartRate = $patient->vitalReadings()
            ->where('type', 'heart_rate')
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get(['id', 'type', 'value', 'unit', 'recorded_at']);

        // Latest log entry per medication — one row per med in history shape
        $today  = now()->toDateString();
        $nowStr = now()->format('Y-m-d H:i');

        $medications = $patient->medications()
            ->where('active', true)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        // One query: latest log per medication_id
        $latestLogs = $patient->medicationLogs()
            ->whereIn('id', function ($q) use ($patient) {
                $q->selectRaw('MAX(id)')
                    ->from('medication_logs')
                    ->where('patient_id', $patient->id)
                    ->groupBy('medication_id');
            })
            ->get()
            ->keyBy('medication_id');

        $medicationLogs = $medications->map(function ($med) use ($latestLogs, $today, $nowStr) {
            $times      = $med->reminder_times ?? [['period' => null, 'time' => '08:00']];
            $latestLog  = $latestLogs->get($med->id);

            if ($latestLog) {
                $logTime     = $latestLog->scheduled_at->format('H:i');
                $matched     = collect($times)->first(fn($item) =>
                    (is_array($item) ? ($item['time'] ?? $item) : $item) === $logTime
                );
                $period = is_array($matched) ? ($matched['period'] ?? null) : null;

                return [
                    'medication_id'  => (int) $med->id,
                    'name'           => $med->name,
                    'dosage'         => $med->dosage,
                    'frequency'      => $med->frequency,
                    'period'         => $period,
                    'scheduled_time' => $logTime,
                    'scheduled_at'   => $latestLog->scheduled_at->format('Y-m-d H:i'),
                    'status'         => $latestLog->status,
                    'taken_at'       => $latestLog->taken_at,
                ];
            }

            // No log yet — show next upcoming slot today
            $next      = collect($times)->first(fn($item) =>
                ($today . ' ' . (is_array($item) ? ($item['time'] ?? '08:00') : $item)) > $nowStr
            ) ?? $times[0] ?? ['period' => null, 'time' => '08:00'];

            $timeStr   = is_array($next) ? ($next['time']   ?? '08:00') : $next;
            $periodStr = is_array($next) ? ($next['period'] ?? null)    : null;

            return [
                'medication_id'  => (int) $med->id,
                'name'           => $med->name,
                'dosage'         => $med->dosage,
                'frequency'      => $med->frequency,
                'period'         => $periodStr,
                'scheduled_time' => $timeStr,
                'scheduled_at'   => $today . ' ' . $timeStr,
                'status'         => 'upcoming',
                'taken_at'       => null,
            ];
        })->values();

        // Latest symptom entries — newest first regardless of date
        $symptoms = $patient->symptoms()
            ->orderByDesc('logged_at')
            ->limit($limit)
            ->get(['id', 'symptom', 'severity', 'severity_label', 'mood', 'logged_at']);

        return ApiResponse::success([
            'heart_rate'      => $heartRate,
            'medication_logs' => $medicationLogs,
            'symptoms'        => $symptoms,
        ]);
    }
}
