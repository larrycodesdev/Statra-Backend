<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\ProcessVitalBatch;
use App\Models\Device;
use App\Services\VitalProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VitalsController extends Controller
{
    public function sync(Request $request, VitalProcessor $processor): JsonResponse
    {
        $data = $request->validate([
            'device_id' => ['required', 'string'],
            'readings'  => ['required', 'array', 'min:1', 'max:500'],
        ]);

        $patient = $request->user()->patient;

        // Verify the device belongs to this patient
        $device = Device::where('device_id', $data['device_id'])
            ->where('patient_id', $patient->id)
            ->where('is_active', true)
            ->first();

        if (!$device) {
            return ApiResponse::error('Device not found or not registered to your account.', 404);
        }

        // Validate and normalize all readings before dispatching
        $normalized = $processor->validateAndNormalize($data['readings']);

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
            'type'  => ['nullable', 'in:heart_rate,spo2,temperature,blood_pressure,steps,sleep_state,hrv'],
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
