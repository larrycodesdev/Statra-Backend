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
        'calories', 'stress',
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

    public function normalizeFromBand(array $readings): array
    {
        $normalized = [];

        foreach ($readings as $index => $raw) {
            if (empty($raw['timestamp'])) {
                throw ValidationException::withMessages([
                    "{$index}.timestamp" => ['timestamp is required.'],
                ]);
            }

            $ts       = Carbon::parse($raw['timestamp']);
            $spo2At   = !empty($raw['spo2Date']) ? Carbon::parse($raw['spo2Date']) : $ts;
            $bpAt     = !empty($raw['bpDate'])   ? Carbon::parse($raw['bpDate'])   : $ts;

            $scalars = [
                ['field' => 'heartRate', 'type' => 'heart_rate',  'unit' => 'bpm',   'at' => $ts,     'float' => false],
                ['field' => 'temp',      'type' => 'temperature', 'unit' => '°C',    'at' => $ts,     'float' => true],
                ['field' => 'steps',     'type' => 'steps',       'unit' => 'steps', 'at' => $ts,     'float' => false],
                ['field' => 'calories',  'type' => 'calories',    'unit' => 'kcal',  'at' => $ts,     'float' => true],
                ['field' => 'spo2',      'type' => 'spo2',        'unit' => '%',     'at' => $spo2At, 'float' => false],
                ['field' => 'stress',    'type' => 'stress',      'unit' => 'index', 'at' => $ts,     'float' => false],
            ];

            foreach ($scalars as $m) {
                $val = $raw[$m['field']] ?? 0;
                if ($val <= 0) continue;
                $normalized[] = [
                    'type'        => $m['type'],
                    'value'       => $m['float'] ? (float) $val : (int) $val,
                    'unit'        => $m['unit'],
                    'recorded_at' => $m['at'],
                ];
            }

            $highBP = $raw['highBP'] ?? 0;
            $lowBP  = $raw['lowBP']  ?? 0;
            if ($highBP > 0 && $lowBP > 0) {
                $normalized[] = [
                    'type'        => 'blood_pressure',
                    'value'       => ['systolic' => (int) $highBP, 'diastolic' => (int) $lowBP],
                    'unit'        => 'mmHg',
                    'recorded_at' => $bpAt,
                ];
            }
        }

        return $normalized;
    }
}
