<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_id'        => ['required', 'string', 'max:100'],
            'device_model'     => ['nullable', 'string', 'max:100'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'platform'         => ['required', 'in:android,ios'],
        ]);

        $patient = $request->user()->patient;

        // New band (different device_id) — deactivate the old one first
        $isNewDevice = !Device::where('device_id', $data['device_id'])
            ->where('patient_id', $patient->id)
            ->exists();

        if ($isNewDevice) {
            $patient->devices()->where('is_active', true)->update(['is_active' => false]);
        }

        $device = Device::updateOrCreate(
            ['device_id' => $data['device_id']],
            [
                'patient_id'       => $patient->id,
                'device_model'     => $data['device_model'] ?? null,
                'firmware_version' => $data['firmware_version'] ?? null,
                'platform'         => $data['platform'],
                'is_active'        => true,
            ]
        );

        return ApiResponse::created([
            'id'               => $device->id,
            'device_id'        => $device->device_id,
            'device_model'     => $device->device_model,
            'firmware_version' => $device->firmware_version,
            'platform'         => $device->platform,
            'is_active'        => $device->is_active,
        ], 'Device registered.');
    }

    public function status(Request $request): JsonResponse
    {
        $devices = $request->user()->patient->devices()
            ->select('id', 'device_id', 'device_model', 'platform', 'last_synced_at', 'is_active')
            ->get();

        return ApiResponse::success($devices);
    }
}
