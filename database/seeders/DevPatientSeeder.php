<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Patient;
use App\Models\PatientNotification;
use App\Models\User;
use App\Models\VitalReading;
use Illuminate\Database\Seeder;

class DevPatientSeeder extends Seeder
{
    public function run(): void
    {
        // ── Test patient user ────────────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'testpatient@statrahealth.com'],
            [
                'first_name' => 'Amara',
                'last_name'  => 'Nwosu',
                'role'       => 'patient',
                'password'   => 'Statra@test@2026',
                'approval_status' => 'approved',
            ]
        );

        $patient = Patient::firstOrCreate(
            ['user_id' => $user->id],
            [
                'genotype'       => 'SS',
                'blood_type'     => 'O+',
                'date_of_birth'  => '1998-03-14',
                'gender'         => 'female',
                'age_group'      => 'adult',
                'calibration_status' => 'calibrating',
            ]
        );

        // ── Vital readings ───────────────────────────────────────────────────
        $tempReading = VitalReading::create([
            'patient_id'       => $patient->id,
            'type'             => 'temperature',
            'value'            => 38.7,
            'unit'             => '°C',
            'recorded_at'      => now()->subHours(5),
            'received_at'      => now()->subHours(5),
            'activity_context' => 'resting',
            'quality_flag'     => 'good',
        ]);

        $spo2Reading = VitalReading::create([
            'patient_id'       => $patient->id,
            'type'             => 'spo2',
            'value'            => 92,
            'unit'             => '%',
            'recorded_at'      => now()->subHours(6),
            'received_at'      => now()->subHours(6),
            'activity_context' => 'resting',
            'quality_flag'     => 'good',
        ]);

        // Healthy readings for context
        foreach ([
            ['type' => 'heart_rate',  'value' => 78,   'unit' => 'bpm',   'activity_context' => 'resting'],
            ['type' => 'heart_rate',  'value' => 112,  'unit' => 'bpm',   'activity_context' => 'active'],
            ['type' => 'hrv',         'value' => 42.5, 'unit' => 'ms',    'activity_context' => 'resting'],
            ['type' => 'steps',       'value' => 3200, 'unit' => 'steps', 'activity_context' => 'active'],
            ['type' => 'temperature', 'value' => 36.8, 'unit' => '°C',    'activity_context' => 'resting'],
            ['type' => 'spo2',        'value' => 97,   'unit' => '%',     'activity_context' => 'resting'],
        ] as $i => $r) {
            VitalReading::create([
                'patient_id'       => $patient->id,
                'type'             => $r['type'],
                'value'            => $r['value'],
                'unit'             => $r['unit'],
                'recorded_at'      => now()->subDays(1)->subMinutes($i * 30),
                'received_at'      => now()->subDays(1)->subMinutes($i * 30),
                'activity_context' => $r['activity_context'],
                'quality_flag'     => 'good',
            ]);
        }

        // ── Alerts ───────────────────────────────────────────────────────────
        $tempAlert = Alert::create([
            'patient_id'       => $patient->id,
            'vital_reading_id' => $tempReading->id,
            'type'             => 'temperature_high',
            'level'            => 1,
            'message'          => 'Urgent: Temperature is 38.7°C — fever ≥38.5°C requires immediate evaluation.',
            'status'           => 'pending',
        ]);

        $spo2Alert = Alert::create([
            'patient_id'       => $patient->id,
            'vital_reading_id' => $spo2Reading->id,
            'type'             => 'spo2_low',
            'level'            => 2,
            'message'          => 'Warning: SpO2 is 92% — below safe threshold.',
            'status'           => 'acknowledged',
        ]);

        // ── Notifications ─────────────────────────────────────────────────────
        PatientNotification::create([
            'patient_id' => $patient->id,
            'type'       => 'alert',
            'title'      => 'High temperature detected',
            'body'       => 'Your temperature reading of 38.7°C has triggered an alert. Please check in with your care team.',
            'data'       => ['alert_id' => $tempAlert->id, 'vital_type' => 'temperature'],
            'read_at'    => null,
        ]);

        PatientNotification::create([
            'patient_id' => $patient->id,
            'type'       => 'medication_reminder',
            'title'      => 'Medication due',
            'body'       => 'Voxelotor 1500mg is due at 11:00am.',
            'data'       => ['medication_id' => 5, 'scheduled_at' => now()->setTime(11, 0)->toDateTimeString()],
            'read_at'    => now()->subMinutes(5),
        ]);

        PatientNotification::create([
            'patient_id' => $patient->id,
            'type'       => 'alert',
            'title'      => 'Low SpO2 detected',
            'body'       => 'Your SpO2 reading of 92% is below the safe threshold. Please rest and monitor your breathing.',
            'data'       => ['alert_id' => $spo2Alert->id, 'vital_type' => 'spo2'],
            'read_at'    => now()->subMinutes(30),
        ]);

        $this->command->info("Dev patient seeded — email: testpatient@statrahealth.com / password: Statra@test@2026");
    }
}
