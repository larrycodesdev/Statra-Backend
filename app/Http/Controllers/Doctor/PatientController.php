<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Alert;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctor   = $request->user()->doctor;
        $patients = Patient::with('user:id,name,email,phone,avatar')
            ->where('assigned_doctor_id', $request->user()->id)
            ->paginate(20);

        return ApiResponse::paginated($patients);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $patient->load([
            'user:id,name,email,phone,avatar,fcm_token',
            'devices:id,patient_id,device_id,device_model,platform,last_synced_at,is_active',
        ]);

        return ApiResponse::success([
            'id'                      => $patient->id,
            'user'                    => $patient->user,
            'genotype'                => $patient->genotype,
            'blood_type'              => $patient->blood_type,
            'date_of_birth'           => $patient->date_of_birth?->format('Y-m-d'),
            'gender'                  => $patient->gender,
            'emergency_contact_name'  => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
            'devices'                 => $patient->devices,
        ]);
    }

    public function vitals(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $request->validate([
            'type'  => ['nullable', 'in:heart_rate,spo2,temperature,blood_pressure,steps,sleep_state,hrv'],
            'range' => ['nullable', 'in:1h,6h,24h,7d,30d'],
        ]);

        $query = $patient->vitalReadings()->orderByDesc('recorded_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $from = match ($request->range) {
            '1h'  => now()->subHour(),
            '6h'  => now()->subHours(6),
            '24h' => now()->subDay(),
            '7d'  => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };

        $vitals = $query->where('recorded_at', '>=', $from)
            ->select('id', 'type', 'value', 'unit', 'recorded_at')
            ->paginate(100);

        return ApiResponse::paginated($vitals);
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

        return ApiResponse::paginated($alerts);
    }

    public function addNote(Request $request, int $id): JsonResponse
    {
        $patient = $this->resolvePatient($request, $id);

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
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

    public function dashboard(Request $request): JsonResponse
    {
        $doctorUserId = $request->user()->id;

        $totalPatients   = Patient::where('assigned_doctor_id', $doctorUserId)->count();
        $criticalAlerts  = Alert::whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $doctorUserId))
            ->where('level', 1)->where('status', 'pending')->count();
        $pendingAlerts   = Alert::whereHas('patient', fn ($q) => $q->where('assigned_doctor_id', $doctorUserId))
            ->where('status', 'pending')->count();

        return ApiResponse::success([
            'total_patients'  => $totalPatients,
            'critical_alerts' => $criticalAlerts,
            'pending_alerts'  => $pendingAlerts,
        ]);
    }

    private function resolvePatient(Request $request, int $id): ?Patient
    {
        return Patient::where('id', $id)
            ->where('assigned_doctor_id', $request->user()->id)
            ->first();
    }
}
