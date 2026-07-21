<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Appointment;
use App\Services\QueryScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(private readonly QueryScope $scope) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'     => ['nullable', 'in:upcoming,completed,cancelled'],
            'patient_id' => ['nullable', 'integer'],
        ]);

        $query = Appointment::with('patient.user:id,name,first_name,last_name,avatar')
            ->orderByDesc('scheduled_at');

        // Scope to visible patients only
        $visiblePatientIds = $this->scope->patients($request)->pluck('id');
        $query->whereIn('patient_id', $visiblePatientIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        $appointments = $query->paginate(20);

        $appointments->through(fn ($a) => $this->formatAppointment($a));

        return ApiResponse::paginated($appointments);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id'   => ['required', 'exists:patients,id'],
            'scheduled_at' => ['required', 'date'],
            'type'         => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $patient = $this->scope->patients($request)
            ->where('patients.id', $data['patient_id'])
            ->first();

        if (!$patient) {
            return ApiResponse::notFound('Patient not found.');
        }

        // Staff cannot create appointments
        if ($request->user()->role === 'staff') {
            return ApiResponse::error('Access denied.', 403);
        }

        $appointment = Appointment::create([
            'patient_id'   => $patient->id,
            'doctor_id'    => $request->user()->id,
            'scheduled_at' => $data['scheduled_at'],
            'type'         => $data['type'] ?? null,
            'status'       => 'upcoming',
            'notes'        => $data['notes'] ?? null,
        ]);

        return ApiResponse::created($this->formatAppointment($appointment), 'Appointment created.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('id', $id)
            ->whereIn('patient_id', $this->scope->patients($request)->pluck('id'))
            ->first();

        if (!$appointment) {
            return ApiResponse::notFound('Appointment not found.');
        }

        if ($request->user()->role === 'staff') {
            return ApiResponse::error('Access denied.', 403);
        }

        $data = $request->validate([
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'type'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'       => ['sometimes', 'in:upcoming,completed,cancelled'],
            'notes'        => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $appointment->update($data);

        return ApiResponse::success($this->formatAppointment($appointment), 'Appointment updated.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $appointment = Appointment::where('id', $id)
            ->whereIn('patient_id', $this->scope->patients($request)->pluck('id'))
            ->first();

        if (!$appointment) {
            return ApiResponse::notFound('Appointment not found.');
        }

        if ($request->user()->role === 'staff') {
            return ApiResponse::error('Access denied.', 403);
        }

        $appointment->delete();

        return ApiResponse::success(null, 'Appointment cancelled.');
    }

    private function formatAppointment(Appointment $appointment): array
    {
        return [
            'id'          => $appointment->id,
            'scheduledAt' => $appointment->scheduled_at?->toISOString(),
            'type'        => $appointment->type,
            'status'      => $appointment->status,
            'notes'       => $appointment->notes,
            'patient'     => $appointment->patient ? [
                'id'        => $appointment->patient->id,
                'displayId' => 'SCW-' . str_pad($appointment->patient->id, 3, '0', STR_PAD_LEFT),
                'name'      => $appointment->patient->user?->name,
                'avatar'    => $appointment->patient->user?->avatar,
            ] : null,
        ];
    }
}
