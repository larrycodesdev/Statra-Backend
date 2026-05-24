<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'medication_name' => ['required', 'string', 'max:255'],
            'dosage'          => ['nullable', 'string', 'max:100'],
            'scheduled_at'    => ['required', 'date'],
            'taken_at'        => ['nullable', 'date'],
            'status'          => ['sometimes', 'in:taken,missed,pending'],
        ]);

        $log = $request->user()->patient->medicationLogs()->create([
            'medication_name' => $data['medication_name'],
            'dosage'          => $data['dosage'] ?? null,
            'scheduled_at'    => $data['scheduled_at'],
            'taken_at'        => $data['taken_at'] ?? null,
            'status'          => $data['status'] ?? 'pending',
        ]);

        return ApiResponse::created($log, 'Medication log recorded.');
    }

    public function index(Request $request): JsonResponse
    {
        $logs = $request->user()->patient
            ->medicationLogs()
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return ApiResponse::paginated($logs);
    }
}
