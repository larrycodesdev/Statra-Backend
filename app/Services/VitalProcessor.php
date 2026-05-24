<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class VitalProcessor
{
    const ALLOWED_TYPES = [
        'heart_rate', 'spo2', 'temperature',
        'blood_pressure', 'steps', 'sleep_state', 'hrv',
    ];

    public function validateAndNormalize(array $readings): array
    {
        $normalized = [];

        foreach ($readings as $index => $reading) {
            $validator = Validator::make($reading, [
                'type'        => ['required', 'string', 'in:' . implode(',', self::ALLOWED_TYPES)],
                'value'       => ['required'],
                'unit'        => ['nullable', 'string', 'max:20'],
                'recorded_at' => ['required', 'date'],
            ]);

            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "readings.{$index}" => $validator->errors()->all(),
                ]);
            }

            $value = $reading['value'];

            // blood_pressure expects {systolic, diastolic}; others are numeric
            if ($reading['type'] === 'blood_pressure') {
                if (!is_array($value) || !isset($value['systolic'], $value['diastolic'])) {
                    throw ValidationException::withMessages([
                        "readings.{$index}.value" => ['blood_pressure requires {systolic, diastolic}.'],
                    ]);
                }
            } else {
                if (!is_numeric($value) && !(is_array($value) && isset($value['value']))) {
                    throw ValidationException::withMessages([
                        "readings.{$index}.value" => ['Value must be numeric for this type.'],
                    ]);
                }
            }

            $normalized[] = [
                'type'        => $reading['type'],
                'value'       => is_array($value) ? $value : (float) $value,
                'unit'        => $reading['unit'] ?? null,
                'recorded_at' => Carbon::parse($reading['recorded_at']),
            ];
        }

        return $normalized;
    }
}
