<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $appointments = Appointment::where('doctor_id', $request->user()->id)
            ->with('patient.user:id,name,avatar')
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return ApiResponse::paginated($appointments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id'   => ['required', 'exists:patients,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        // Ensure patient belongs to this doctor
        $patient = Patient::where('id', $data['patient_id'])
            ->where('assigned_doctor_id', $request->user()->id)
            ->first();

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        $appointment = Appointment::create([
            'patient_id'   => $patient->id,
            'doctor_id'    => $request->user()->id,
            'scheduled_at' => $data['scheduled_at'],
            'status'       => 'upcoming',
            'notes'        => $data['notes'] ?? null,
        ]);

        return ApiResponse::created($appointment, 'Appointment created.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('id', $id)
            ->where('doctor_id', $request->user()->id)
            ->first();

        if (!$appointment) {
            return ApiResponse::notFound('Appointment not found.');
        }

        $data = $request->validate([
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'status'       => ['sometimes', 'in:upcoming,completed,cancelled'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $appointment->update($data);

        return ApiResponse::success($appointment, 'Appointment updated.');
    }
}
