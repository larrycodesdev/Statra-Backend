<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user()->load('patient.assignedDoctor.doctor');
        $patient = $user->patient;

        return ApiResponse::success([
            // Personal info
            'id'         => $user->id,
            'uuid'       => $user->uuid,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'name'       => $user->full_name,
            'username'   => $user->username,
            'email'      => $user->email,
            'phone'      => $user->phone,
            'avatar'     => $user->avatar,
            'gender'     => $patient->gender,
            'age'        => $patient->age,
            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),

            // Medical info
            'genotype'   => $patient->genotype,
            'blood_type' => $patient->blood_type,
            'condition'  => $patient->condition ?? [],

            // Emergency contact
            'emergency_contact' => [
                'name'         => $patient->emergency_contact_name,
                'phone'        => $patient->emergency_contact_phone,
                'email'        => $patient->emergency_contact_email,
                'address'      => $patient->emergency_contact_address,
                'relationship' => $patient->emergency_contact_relationship,
            ],

            // Care team
            'care_team' => $this->careTeam($patient),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'string', 'max:100'],
            'username'   => ['sometimes', 'string', 'max:30', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar'     => ['sometimes', 'nullable', 'url', 'max:500'],
            'gender'     => ['sometimes', 'in:male,female,other'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
        ]);

        // Sync name if first/last updated
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? $user->first_name;
            $last  = $data['last_name'] ?? $user->last_name;
            $data['name'] = trim("$first $last");
        }

        $userFields    = array_intersect_key($data, array_flip(['first_name', 'last_name', 'name', 'username', 'phone', 'avatar']));
        $patientFields = array_intersect_key($data, array_flip(['gender', 'date_of_birth']));

        if ($userFields) {
            $user->update($userFields);
        }

        if ($patientFields) {
            $user->patient->update($patientFields);
        }

        return ApiResponse::success(null, 'Profile updated successfully.');
    }

    public function updateEmergencyContacts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string', 'max:500'],
            'relationship' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->patient->update([
            'emergency_contact_name'         => $data['name'],
            'emergency_contact_phone'        => $data['phone'],
            'emergency_contact_email'        => $data['email'] ?? null,
            'emergency_contact_address'      => $data['address'] ?? null,
            'emergency_contact_relationship' => $data['relationship'] ?? null,
        ]);

        return ApiResponse::success(null, 'Emergency contact updated.');
    }

    public function updateMedicalInfo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'genotype'   => ['sometimes', 'in:SS,SC,SB,SD,SE,SO,other'],
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:10'],
            'condition'  => ['sometimes', 'nullable', 'array'],
            'condition.*' => ['string', 'max:100'],
        ]);

        $request->user()->patient->update($data);

        return ApiResponse::success(null, 'Medical info updated.');
    }

    public function updateFcmToken(Request $request, NotificationService $notificationService): JsonResponse
    {
        $request->validate(['fcm_token' => ['required', 'string']]);

        $notificationService->updateFcmToken($request->user(), $request->fcm_token);

        return ApiResponse::success(null, 'FCM token updated.');
    }

    private function careTeam(\App\Models\Patient $patient): array
    {
        if (!$patient->assigned_doctor_id) {
            return [];
        }

        $doctor = $patient->assignedDoctor;
        if (!$doctor) {
            return [];
        }

        return [[
            'id'             => $doctor->id,
            'name'           => $doctor->full_name,
            'avatar'         => $doctor->avatar,
            'specialisation' => $doctor->doctor?->specialisation,
            'hospital'       => $doctor->doctor?->hospital_name,
        ]];
    }
}
