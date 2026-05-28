<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $settings = $request->user()->patient->settings
            ?? $request->user()->patient->settings()->create([]);

        return ApiResponse::success($this->settingsResource($settings));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'allow_doctor_view_records' => ['sometimes', 'boolean'],
            'allow_doctor_view_data'    => ['sometimes', 'boolean'],
            'share_symptom_pain_data'   => ['sometimes', 'boolean'],
            'share_medication_records'  => ['sometimes', 'boolean'],
            'reminder_enabled'          => ['sometimes', 'boolean'],
            'smart_alert_enabled'       => ['sometimes', 'boolean'],
        ]);

        $patient  = $request->user()->patient;
        $settings = $patient->settings ?? $patient->settings()->create([]);

        $settings->update($data);

        return ApiResponse::success($this->settingsResource($settings->fresh()), 'Settings updated.');
    }

    private function settingsResource(\App\Models\PatientSettings $settings): array
    {
        return [
            'allow_doctor_view_records' => $settings->allow_doctor_view_records,
            'allow_doctor_view_data'    => $settings->allow_doctor_view_data,
            'share_symptom_pain_data'   => $settings->share_symptom_pain_data,
            'share_medication_records'  => $settings->share_medication_records,
            'reminder_enabled'          => $settings->reminder_enabled,
            'smart_alert_enabled'       => $settings->smart_alert_enabled,
        ];
    }
}
