<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\ProcessVitalBatch;
use App\Services\VitalProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VitalsController extends Controller
{
    public function sync(Request $request, VitalProcessor $processor): JsonResponse
    {
        $readings = $request->json()->all();

        if (!is_array($readings) || empty($readings)) {
            return ApiResponse::error('Request body must be a non-empty JSON array.', 422);
        }

        if (count($readings) > 500) {
            return ApiResponse::error('Maximum 500 readings per batch.', 422);
        }

        $patient = $request->user()->patient;

        $device = $patient->devices()->where('is_active', true)->first();

        if (!$device) {
            return ApiResponse::error('No active band registered. Please register your device first.', 404);
        }

        $normalized = $processor->normalizeFromBand($readings);

        if (empty($normalized)) {
            return ApiResponse::success(['queued' => 0], 'No valid readings to process.');
        }

        ProcessVitalBatch::dispatch($normalized, $patient->id, $device->id);

        return ApiResponse::success(
            ['queued' => count($normalized)],
            'Vitals accepted for processing.',
            202
        );
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type'  => ['nullable', 'in:heart_rate,spo2,temperature,blood_pressure,steps,sleep_state,hrv,calories,stress'],
            'range' => ['nullable', 'in:1h,6h,24h,7d,30d'],
        ]);

        $patient = $request->user()->patient;
        $query   = $patient->vitalReadings()->orderByDesc('recorded_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $from = match ($request->range) {
            '1h'  => now()->subHour(),
            '6h'  => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d'  => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        $query->where('recorded_at', '>=', $from);

        $readings = $query->select('id', 'type', 'value', 'unit', 'recorded_at')
            ->paginate(100);

        return ApiResponse::paginated($readings);
    }
}
