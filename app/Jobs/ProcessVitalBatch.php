<?php

namespace App\Jobs;

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
        $alerts  = [];

        foreach ($this->readings as $reading) {
            $vitalReading = VitalReading::create([
                'patient_id'  => $patient->id,
                'device_id'   => $device->id,
                'type'        => $reading['type'],
                'value'       => $reading['value'],
                'unit'        => $reading['unit'] ?? null,
                'recorded_at' => $reading['recorded_at'],
            ]);

            $alert = $alertEngine->evaluate($vitalReading, $patient);
            if ($alert) {
                $alerts[] = $alert->id;
            }
        }

        $device->update(['last_synced_at' => now()]);
    }
}
