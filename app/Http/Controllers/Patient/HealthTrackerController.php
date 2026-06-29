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

        // Latest medication logs — includes name and status regardless of date
        $medicationLogs = $patient->medicationLogs()
            ->orderByDesc('scheduled_at')
            ->limit($limit)
            ->get(['id', 'medication_id', 'medication_name', 'dosage', 'status', 'scheduled_at', 'taken_at']);

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
