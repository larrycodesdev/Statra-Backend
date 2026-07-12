<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Patient;
use App\Models\VitalReading;
use App\Services\AlertEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessVitalBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly array $readings,
        public readonly int $patientId,
        public readonly int $deviceId,
    ) {}

    public function handle(AlertEngine $alertEngine): void
    {
        $patient = Patient::findOrFail($this->patientId);
        $device  = Device::findOrFail($this->deviceId);

        foreach ($this->readings as $reading) {
            $vitalReading = VitalReading::create([
                'patient_id'       => $patient->id,
                'device_id'        => $device->id,
                'type'             => $reading['type'],
                'value'            => $reading['value'],
                'unit'             => $reading['unit'] ?? null,
                'recorded_at'      => $reading['recorded_at'],
                'received_at'      => now(),
                'activity_context' => $reading['activity_context'] ?? null,
                'quality_flag'     => $reading['quality_flag'] ?? 'good',
            ]);

            // Immediate hard threshold: temp >= 38.5°C → urgent alert regardless of baseline
            if ($vitalReading->type === 'temperature') {
                $tempValue = is_array($vitalReading->value)
                    ? (float) ($vitalReading->value['value'] ?? 0)
                    : (float) $vitalReading->value;

                if ($tempValue >= 38.5) {
                    Alert::firstOrCreate(
                        [
                            'patient_id'       => $patient->id,
                            'vital_reading_id' => $vitalReading->id,
                            'type'             => 'temperature_high',
                        ],
                        [
                            'level'   => 1,
                            'message' => "Urgent: Temperature is {$tempValue}°C — fever ≥38.5°C requires immediate evaluation.",
                            'status'  => 'pending',
                        ]
                    );
                    SendAlertNotification::dispatch($vitalReading->id);
                    continue;
                }
            }

            $alert = $alertEngine->evaluate($vitalReading, $patient);
            if ($alert) {
                SendAlertNotification::dispatch($alert->id);
            }
        }

        $device->update(['last_synced_at' => now()]);
    }
}
