<?php

namespace App\Services;

use App\Jobs\SendAlertNotification;
use App\Models\Alert;
use App\Models\Patient;
use App\Models\VitalReading;

class AlertEngine
{
    public const THRESHOLDS = [
        'spo2' => [
            'critical' => 90,
            'warning'  => 93,
        ],
        'heart_rate' => [
            'critical_low'  => 40,
            'warning_low'   => 50,
            'warning_high'  => 120,
            'critical_high' => 150,
        ],
        'temperature' => [
            'warning'  => 37.8,
            'critical' => 38.5,
        ],
    ];

    public function evaluate(VitalReading $reading, Patient $patient): ?Alert
    {
        $alert = match ($reading->type) {
            'spo2'        => $this->evaluateSpo2($reading, $patient),
            'heart_rate'  => $this->evaluateHeartRate($reading, $patient),
            'temperature' => $this->evaluateTemperature($reading, $patient),
            default       => null,
        };

        if ($alert) {
            SendAlertNotification::dispatch($alert->id);
        }

        return $alert;
    }

    private function evaluateSpo2(VitalReading $reading, Patient $patient): ?Alert
    {
        $value = $this->numericValue($reading->value);
        $thresholds = self::THRESHOLDS['spo2'];

        if ($value <= $thresholds['critical']) {
            return $this->createAlert($patient, $reading, 'spo2_low', 1,
                "Critical: SpO2 is {$value}% — dangerously low oxygen saturation.");
        }

        if ($value <= $thresholds['warning']) {
            return $this->createAlert($patient, $reading, 'spo2_low', 2,
                "Warning: SpO2 is {$value}% — below safe threshold.");
        }

        return null;
    }

    private function evaluateHeartRate(VitalReading $reading, Patient $patient): ?Alert
    {
        $value = $this->numericValue($reading->value);
        $t = self::THRESHOLDS['heart_rate'];

        if ($value <= $t['critical_low']) {
            return $this->createAlert($patient, $reading, 'heart_rate_low', 1,
                "Critical: Heart rate is {$value} bpm — severely bradycardic.");
        }

        if ($value >= $t['critical_high']) {
            return $this->createAlert($patient, $reading, 'heart_rate_high', 1,
                "Critical: Heart rate is {$value} bpm — severely tachycardic.");
        }

        if ($value <= $t['warning_low']) {
            return $this->createAlert($patient, $reading, 'heart_rate_low', 2,
                "Warning: Heart rate is {$value} bpm — low.");
        }

        if ($value >= $t['warning_high']) {
            return $this->createAlert($patient, $reading, 'heart_rate_high', 2,
                "Warning: Heart rate is {$value} bpm — elevated.");
        }

        return null;
    }

    private function evaluateTemperature(VitalReading $reading, Patient $patient): ?Alert
    {
        $value = $this->numericValue($reading->value);
        $t = self::THRESHOLDS['temperature'];

        if ($value >= $t['critical']) {
            return $this->createAlert($patient, $reading, 'temperature_high', 1,
                "Critical: Temperature is {$value}°C — high fever.");
        }

        if ($value >= $t['warning']) {
            return $this->createAlert($patient, $reading, 'temperature_high', 2,
                "Warning: Temperature is {$value}°C — elevated.");
        }

        return null;
    }

    private function createAlert(Patient $patient, VitalReading $reading, string $type, int $level, string $message): Alert
    {
        return Alert::create([
            'patient_id'       => $patient->id,
            'vital_reading_id' => $reading->id,
            'type'             => $type,
            'level'            => $level,
            'message'          => $message,
            'status'           => 'pending',
        ]);
    }

    private function numericValue(mixed $value): float
    {
        // value is stored as json — could be a plain number or an array
        return is_array($value) ? (float) ($value['value'] ?? 0) : (float) $value;
    }
}
