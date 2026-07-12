<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Models\CompositeDeviationScore;
use App\Models\Patient;
use App\Services\QueryScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PatientController extends Controller
{
    public function __construct(private readonly QueryScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $patients = $this->scope->patients($request)
            ->with([
                'user:id,name,first_name,last_name,email,phone,avatar',
                'assignedDoctor:id,name,first_name,last_name',
                'assignedNurse:id,name,first_name,last_name',
            ])
            ->paginate(20);

        $patients->through(fn ($p) => $this->patientCard($p));

        return ApiResponse::paginated($patients);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admin and superadmin can create patients from the dashboard
        if (!in_array($user->role, ['admin', 'superadmin'])) {
            return ApiResponse::error('Access denied.', 403);
        }

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'genotype'       => ['nullable', 'string', 'max:10'],
            'blood_type'     => ['nullable', 'string', 'max:10'],
            'date_of_birth'  => ['nullable', 'date'],
            'gender'         => ['nullable', 'in:male,female,other'],
            'hospital_id'    => ['nullable', 'exists:hospitals,id'],
            'assigned_doctor_id' => ['nullable', 'exists:users,id'],
        ]);

        $patientUser = \App\Models\User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => 'patient',
            'phone'    => $data['phone'] ?? null,
        ]);

        $patient = Patient::create([
            'user_id'            => $patientUser->id,
            'genotype'           => $data['genotype'] ?? null,
            'blood_type'         => $data['blood_type'] ?? null,
            'date_of_birth'      => $data['date_of_birth'] ?? null,
            'gender'             => $data['gender'] ?? null,
            'hospital_id'        => $data['hospital_id'] ?? ($user->role === 'admin' ? $user->hospital_id : null),
            'assigned_doctor_id' => $data['assigned_doctor_id'] ?? null,
        ]);

        return ApiResponse::created($this->patientCard($patient->load('user')), 'Patient created.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $patient->load([
            'user:id,name,first_name,last_name,email,phone,avatar',
            'assignedDoctor:id,name,first_name,last_name',
            'assignedNurse:id,name,first_name,last_name',
            'devices:id,patient_id,device_id,device_model,platform,last_synced_at,is_active',
            'emergencyContacts:id,patient_id,name,phone,relationship',
        ]);

        $latestScore = CompositeDeviationScore::where('patient_id', $patient->id)
            ->orderByDesc('computed_at')->first();
        $latestAlert = Alert::where('patient_id', $patient->id)
            ->where('status', 'pending')
            ->orderBy('level')->first();
        $nextAppt = $patient->appointments()
            ->where('status', 'upcoming')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')->first();

        return ApiResponse::success([
            'id'              => $patient->id,
            'displayId'       => $this->displayId($patient->id),
            'healthScore'     => $this->healthScore($latestScore),
            'alertLevel'      => $this->alertLevel($latestAlert),
            'calibrationStatus' => $patient->calibration_status,
            // Demographics
            'name'            => $patient->user?->name,
            'email'           => $patient->user?->email,
            'phone'           => $patient->user?->phone,
            'avatar'          => $patient->user?->avatar,
            'dateOfBirth'     => $patient->date_of_birth?->format('Y-m-d'),
            'age'             => $patient->age,
            'gender'          => $patient->gender,
            // Clinical
            'sickleCellType'  => $patient->genotype,
            'genotype'        => $patient->genotype,
            'bloodType'       => $patient->blood_type,
            'condition'       => $patient->condition,
            // Hospital
            'ward'            => $patient->ward,
            'admittedAt'      => $patient->admitted_at?->format('Y-m-d'),
            'assignedDoctor'  => $this->staffName($patient->assignedDoctor),
            'assignedNurse'   => $this->staffName($patient->assignedNurse),
            'nextAppointmentAt' => $nextAppt?->scheduled_at?->toISOString(),
            // Devices & contacts
            'devices'         => $patient->devices,
            'emergencyContacts' => $patient->emergencyContacts,
        ]);
    }

    public function vitals(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $request->validate([
            'range' => ['nullable', 'in:1h,6h,24h,7d,30d'],
        ]);

        $from = match ($request->range) {
            '1h'  => now()->subHour(),
            '6h'  => now()->subHours(6),
            '7d'  => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(), // 24h default
        };

        $readings = $patient->vitalReadings()
            ->where('recorded_at', '>=', $from)
            ->orderByDesc('recorded_at')
            ->get(['id', 'type', 'value', 'unit', 'recorded_at', 'activity_context']);

        // Reshape into per-type snapshot with series
        $types  = ['heart_rate', 'spo2', 'temperature', 'hrv', 'steps', 'sleep_state'];
        $keys   = ['heart_rate' => 'heartRate', 'spo2' => 'spo2', 'temperature' => 'temperature',
                   'hrv' => 'hrv', 'steps' => 'steps', 'sleep_state' => 'sleepState'];
        $units  = ['heart_rate' => 'bpm', 'spo2' => '%', 'temperature' => '°C',
                   'hrv' => 'ms', 'steps' => 'steps', 'sleep_state' => null];

        $snapshot = [];
        foreach ($types as $type) {
            $typeReadings = $readings->where('type', $type)->values();
            $latest       = $typeReadings->first();

            $snapshot[$keys[$type]] = [
                'current'    => $latest ? $latest->value : null,
                'unit'       => $units[$type],
                'recordedAt' => $latest?->recorded_at,
                'series'     => $typeReadings->take(100)->map(fn ($r) => [
                    'value'       => $r->value,
                    'recordedAt'  => $r->recorded_at,
                ])->values(),
            ];
        }

        return ApiResponse::success($snapshot);
    }

    public function alerts(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $alerts = $patient->alerts()
            ->with('vitalReading:id,type,value,unit,recorded_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        $alerts->through(fn ($a) => [
            'id'         => $a->id,
            'level'      => $this->mapLevel($a->level),
            'type'       => $a->type,
            'message'    => $a->message,
            'status'     => $a->status,
            'resolvedAt' => $a->resolved_at?->toISOString(),
            'createdAt'  => $a->created_at->toISOString(),
            'vital'      => $a->vitalReading ? [
                'type'  => $a->vitalReading->type,
                'value' => $a->vitalReading->value,
                'unit'  => $a->vitalReading->unit,
            ] : null,
        ]);

        return ApiResponse::paginated($alerts);
    }

    public function medications(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $medications = $patient->medications()
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($medications);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        if (!$this->scope->canWrite($request, $patient)) {
            return ApiResponse::error('Access denied. Read-only role cannot add notes.', 403);
        }

        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $record = $patient->medicalRecords()->create([
            'title'       => $data['title'],
            'content'     => $data['content'],
            'recorded_by' => $request->user()->id,
        ]);

        return ApiResponse::created($record, 'Medical note added.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolvePatient(Request $request, int $id): ?Patient
    {
        return $this->scope->patients($request)->where('patients.id', $id)->first();
    }

    private function patientCard(Patient $patient): array
    {
        $latestScore = CompositeDeviationScore::where('patient_id', $patient->id)
            ->orderByDesc('computed_at')->first();
        $latestAlert = Alert::where('patient_id', $patient->id)
            ->where('status', 'pending')
            ->orderBy('level')->first();

        return [
            'id'          => $patient->id,
            'displayId'   => $this->displayId($patient->id),
            'name'        => $patient->user?->name,
            'avatar'      => $patient->user?->avatar,
            'age'         => $patient->age,
            'gender'      => $patient->gender,
            'genotype'    => $patient->genotype,
            'ward'        => $patient->ward,
            'admittedAt'  => $patient->admitted_at?->format('Y-m-d'),
            'healthScore' => $this->healthScore($latestScore),
            'alertLevel'  => $this->alertLevel($latestAlert),
            'calibrationStatus' => $patient->calibration_status,
            'assignedDoctor' => $this->staffName($patient->assignedDoctor),
            'assignedNurse'  => $this->staffName($patient->assignedNurse),
        ];
    }

    private function healthScore(?CompositeDeviationScore $score): ?int
    {
        if (!$score) return null;

        return match ($score->status) {
            'urgent'   => 20,
            'elevated' => 45,
            'watch'    => 65,
            'stable'   => 85,
            default    => null,
        };
    }

    private function alertLevel(?Alert $alert): string
    {
        if (!$alert) return 'L3';

        return match ((int) $alert->level) {
            1 => 'L1',
            2 => 'L2',
            default => 'L3',
        };
    }

    private function mapLevel(int $level): string
    {
        return match ($level) {
            1 => 'L1',
            2 => 'L2',
            default => 'L3',
        };
    }

    private function displayId(int $patientId): string
    {
        return 'SCW-' . str_pad($patientId, 3, '0', STR_PAD_LEFT);
    }

    private function staffName(?object $user): ?array
    {
        if (!$user) return null;

        return [
            'id'   => $user->id,
            'name' => trim("{$user->first_name} {$user->last_name}") ?: $user->name,
        ];
    }
}
