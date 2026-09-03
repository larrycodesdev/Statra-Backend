<?php

namespace Database\Seeders;

use App\Models\CompositeDeviationScore;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientBaseline;
use App\Models\PatientNotification;
use App\Models\PatientSettings;
use App\Models\Symptom;
use App\Models\User;
use App\Models\VitalReading;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MobileDevTestSeeder extends Seeder
{
    public function run(): void
    {
        // ── Test account ──────────────────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'testpatient@statra.dev'],
            [
                'first_name'        => 'Adaeze',
                'last_name'         => 'Okonkwo',
                'name'              => 'Adaeze Okonkwo',
                'username'          => 'adaeze_test',
                'password'          => Hash::make('Test@1234'),
                'role'              => 'patient',
                'email_verified_at' => now(),
            ]
        );

        $patient = Patient::updateOrCreate(
            ['user_id' => $user->id],
            [
                'genotype'             => 'SS',
                'blood_type'           => 'O+',
                'date_of_birth'        => '1995-04-12',
                'gender'               => 'female',
                'condition'            => ['sickle_cell'],
                'calibration_status'   => 'active',
                'calibration_start_at' => now()->subDays(30),
                'age_group'            => 'adult',
            ]
        );

        PatientSettings::firstOrCreate(['patient_id' => $patient->id]);

        $pid = $patient->id;

        // ── Baselines (one per signal type) ──────────────────────────────────
        $baselines = [
            ['signal_type' => 'temperature', 'rolling_mean' => 36.6,  'rolling_stddev' => 0.3,  'rolling_variance' => 0.09, 'baseline_confidence' => 'high',   'sample_count' => 240],
            ['signal_type' => 'spo2',        'rolling_mean' => 97.2,  'rolling_stddev' => 1.1,  'rolling_variance' => 1.21, 'baseline_confidence' => 'high',   'sample_count' => 240],
            ['signal_type' => 'heart_rate',  'rolling_mean' => 78.0,  'rolling_stddev' => 8.5,  'rolling_variance' => 72.25,'baseline_confidence' => 'high',   'sample_count' => 240],
            ['signal_type' => 'hrv',         'rolling_mean' => 42.0,  'rolling_stddev' => 6.0,  'rolling_variance' => 36.0, 'baseline_confidence' => 'medium', 'sample_count' => 120],
            ['signal_type' => 'steps',        'rolling_mean' => 3200.0,'rolling_stddev' => 800.0,'rolling_variance' => 640000.0,'baseline_confidence' => 'medium','sample_count' => 120],
        ];

        foreach ($baselines as $b) {
            PatientBaseline::firstOrCreate(
                ['patient_id' => $pid, 'signal_type' => $b['signal_type'], 'activity_context' => 'resting'],
                array_merge($b, ['patient_id' => $pid, 'activity_context' => 'resting', 'window_days' => 14, 'last_updated_at' => now()])
            );
        }

        // ── Composite deviation scores — 14 days ─────────────────────────────
        CompositeDeviationScore::where('patient_id', $pid)->delete();

        $scoreProfiles = [
            // [total_score, status, confidence, outreach]
            [0.4, 'stable',   'high',   false],
            [0.6, 'stable',   'high',   false],
            [0.5, 'stable',   'high',   false],
            [0.8, 'watch',    'medium', false],
            [1.2, 'watch',    'medium', false],
            [2.1, 'elevated', 'medium', true],
            [1.8, 'elevated', 'medium', false],
            [1.1, 'watch',    'high',   false],
            [0.7, 'stable',   'high',   false],
            [0.5, 'stable',   'high',   false],
            [0.4, 'stable',   'high',   false],
            [0.9, 'watch',    'high',   false],
            [0.6, 'stable',   'high',   false],
            [0.5, 'stable',   'high',   false],
        ];

        foreach ($scoreProfiles as $i => [$score, $status, $confidence, $outreach]) {
            $day = now()->subDays(13 - $i)->setTime(14, 0, 0);
            CompositeDeviationScore::create([
                'patient_id'            => $pid,
                'computed_at'           => $day,
                'temp_z'                => round(($score * 0.3) + (rand(-10, 10) / 100), 2),
                'spo2_z'                => round(($score * 0.25) + (rand(-10, 10) / 100), 2),
                'hr_z'                  => round(($score * 0.25) + (rand(-10, 10) / 100), 2),
                'hrv_z'                 => round(($score * 0.1) + (rand(-10, 10) / 100), 2),
                'activity_z'            => round(($score * 0.1) + (rand(-10, 10) / 100), 2),
                'temp_contribution'     => round($score * 0.30, 2),
                'spo2_contribution'     => round($score * 0.25, 2),
                'hr_contribution'       => round($score * 0.25, 2),
                'hrv_contribution'      => round($score * 0.10, 2),
                'activity_contribution' => round($score * 0.10, 2),
                'total_score'           => $score,
                'status'                => $status,
                'confidence'            => $confidence,
                'temperature_absolute'  => round(36.6 + ($score * 0.4), 1),
                'outreach_recommended'  => $outreach,
                'outreach_reason'       => $outreach ? 'Elevated deviation sustained over 24h' : null,
            ]);
        }

        // ── Vital readings — 7 days, 6 readings per day ───────────────────────
        VitalReading::where('patient_id', $pid)->delete();

        for ($day = 6; $day >= 0; $day--) {
            foreach ([7, 10, 13, 16, 19, 22] as $hour) {
                $at = now()->subDays($day)->setTime($hour, rand(0, 59), 0);

                VitalReading::insert([
                    ['patient_id' => $pid, 'device_id' => null, 'type' => 'heart_rate',  'value' => json_encode(['value' => rand(70, 95)]),          'unit' => 'bpm',  'recorded_at' => $at, 'received_at' => $at, 'activity_context' => 'resting', 'quality_flag' => 'good'],
                    ['patient_id' => $pid, 'device_id' => null, 'type' => 'spo2',         'value' => json_encode(['value' => rand(95, 99)]),          'unit' => '%',    'recorded_at' => $at, 'received_at' => $at, 'activity_context' => 'resting', 'quality_flag' => 'good'],
                    ['patient_id' => $pid, 'device_id' => null, 'type' => 'temperature',  'value' => json_encode(['value' => round(36.2 + (rand(0, 8) / 10), 1)]), 'unit' => '°C',   'recorded_at' => $at, 'received_at' => $at, 'activity_context' => 'resting', 'quality_flag' => 'good'],
                    ['patient_id' => $pid, 'device_id' => null, 'type' => 'hrv',          'value' => json_encode(['value' => rand(35, 55)]),          'unit' => 'ms',   'recorded_at' => $at, 'received_at' => $at, 'activity_context' => 'resting', 'quality_flag' => 'good'],
                ]);
            }
        }

        // ── Medications ───────────────────────────────────────────────────────
        Medication::where('patient_id', $pid)->delete();

        $meds = [
            ['name' => 'Hydroxyurea',  'dosage' => '500mg', 'frequency' => 'daily',    'times' => ['08:00']],
            ['name' => 'Folic Acid',   'dosage' => '5mg',   'frequency' => 'daily',    'times' => ['08:00']],
            ['name' => 'Penicillin V', 'dosage' => '250mg', 'frequency' => 'twice',    'times' => ['07:00', '19:00']],
            ['name' => 'Ibuprofen',    'dosage' => '400mg', 'frequency' => 'as_needed','times' => []],
        ];

        foreach ($meds as $m) {
            Medication::create([
                'patient_id'      => $pid,
                'name'            => $m['name'],
                'dosage'          => $m['dosage'],
                'frequency'       => $m['frequency'],
                'frequency_count' => count($m['times']) ?: 1,
                'reminder_times'  => $m['times'],
                'remind_me'       => true,
                'active'          => true,
                'begin_date'      => now()->subDays(30)->toDateString(),
            ]);
        }

        // ── Symptoms — past 10 days ────────────────────────────────────────────
        Symptom::where('patient_id', $pid)->delete();

        $symptoms = [
            ['symptom' => 'fatigue',      'severity' => 2, 'severity_label' => 'mild',     'pain_level' => 2, 'mood' => 'okay',    'days_ago' => 1],
            ['symptom' => 'joint_pain',   'severity' => 4, 'severity_label' => 'moderate', 'pain_level' => 5, 'mood' => 'low',     'days_ago' => 2],
            ['symptom' => 'headache',     'severity' => 3, 'severity_label' => 'moderate', 'pain_level' => 4, 'mood' => 'okay',    'days_ago' => 3],
            ['symptom' => 'chest_pain',   'severity' => 6, 'severity_label' => 'severe',   'pain_level' => 7, 'mood' => 'low',     'days_ago' => 5],
            ['symptom' => 'fatigue',      'severity' => 3, 'severity_label' => 'moderate', 'pain_level' => 3, 'mood' => 'low',     'days_ago' => 7],
            ['symptom' => 'dizziness',    'severity' => 2, 'severity_label' => 'mild',     'pain_level' => 2, 'mood' => 'alright', 'days_ago' => 9],
        ];

        foreach ($symptoms as $s) {
            Symptom::create([
                'patient_id'    => $pid,
                'symptom'       => $s['symptom'],
                'severity'      => $s['severity'],
                'severity_label'=> $s['severity_label'],
                'pain_level'    => $s['pain_level'],
                'mood'          => $s['mood'],
                'body_locations'=> ['chest', 'joints'],
                'pain_areas'    => ['lower_back', 'legs'],
                'triggers'      => ['cold_weather', 'dehydration'],
                'on_medication' => true,
                'logged_at'     => now()->subDays($s['days_ago'])->setTime(10, 0, 0),
            ]);
        }

        // ── Notifications ─────────────────────────────────────────────────────
        PatientNotification::where('patient_id', $pid)->delete();

        $notifications = [
            ['type' => 'alert',              'title' => 'Elevated Deviation Detected',   'body' => 'Your composite score reached 2.1 — elevated. Consider resting and staying hydrated.', 'read_at' => null,  'days_ago' => 6],
            ['type' => 'medication_reminder','title' => 'Hydroxyurea Due',               'body' => 'Time to take your Hydroxyurea 500mg dose.',                                            'read_at' => now(), 'days_ago' => 1],
            ['type' => 'medication_reminder','title' => 'Folic Acid Due',                'body' => 'Time to take your Folic Acid 5mg dose.',                                               'read_at' => now(), 'days_ago' => 2],
            ['type' => 'system',             'title' => 'Baseline Calibration Complete', 'body' => 'Your health baseline is now fully calibrated. Scores are now personalised to you.',    'read_at' => now(), 'days_ago' => 14],
            ['type' => 'alert',              'title' => 'Low SpO₂ Detected',             'body' => 'SpO₂ reading of 94% recorded. If this persists, contact your care team.',              'read_at' => now(), 'days_ago' => 5],
            ['type' => 'system',             'title' => 'Weekly Summary Ready',          'body' => 'Your health summary for last week is available in the Trends section.',                 'read_at' => null,  'days_ago' => 0],
            ['type' => 'medication_reminder','title' => 'Penicillin V Due',              'body' => 'Morning dose of Penicillin V 250mg is due.',                                           'read_at' => null,  'days_ago' => 0],
        ];

        foreach ($notifications as $n) {
            PatientNotification::create([
                'patient_id' => $pid,
                'type'       => $n['type'],
                'title'      => $n['title'],
                'body'       => $n['body'],
                'data'       => null,
                'read_at'    => $n['read_at'],
                'created_at' => now()->subDays($n['days_ago'])->subHours(rand(0, 3)),
                'updated_at' => now()->subDays($n['days_ago'])->subHours(rand(0, 3)),
            ]);
        }

        $this->command->info('✓ Test patient seeded.');
        $this->command->info('  Email:    testpatient@statra.dev');
        $this->command->info('  Password: Test@1234');
        $this->command->info("  Patient ID: {$pid}");
    }
}
