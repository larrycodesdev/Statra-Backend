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

        // Medication plans with today's status — populated as soon as patient adds a medication
        $today      = now()->toDateString();
        $todayLogs  = $patient->medicationLogs()
            ->whereDate('scheduled_at', $today)
            ->get()
            ->groupBy('medication_id');

        $medicationLogs = $patient->medications()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'dosage', 'frequency', 'reminder_times', 'active'])
            ->map(function ($med) use ($todayLogs) {
                $expectedDoses = count($med->reminder_times ?? ['08:00']);
                $logs          = $todayLogs->get($med->id, collect());
                $takenCount    = $logs->where('status', 'taken')->count();
                $missedCount   = $logs->where('status', 'missed')->count();

                $todayStatus = match (true) {
                    ($takenCount + $missedCount) === 0 => 'pending',
                    $takenCount === $expectedDoses     => 'taken',
                    $missedCount === $expectedDoses    => 'missed',
                    default                            => 'partial',
                };

                return [
                    'id'           => $med->id,
                    'name'         => $med->name,
                    'dosage'       => $med->dosage,
                    'frequency'    => $med->frequency,
                    'today_status' => $todayStatus,
                    'active'       => (bool) $med->active,
                ];
            });

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
