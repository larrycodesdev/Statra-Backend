<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user()->load('patient.assignedDoctor.doctor', 'patient.assignedNurse', 'patient.emergencyContacts');
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

            // Emergency contacts
            'emergency_contacts' => $patient->emergencyContacts->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'phone'        => $c->phone,
                'email'        => $c->email,
                'address'      => $c->address,
                'relationship' => $c->relationship,
            ])->values()->all(),

            // Care team
            'care_team' => $this->buildCareTeam($patient),
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
            'avatar'     => ['sometimes', 'nullable', 'string', 'max:500'],
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

    public function addEmergencyContact(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;

        if ($patient->emergencyContacts()->count() >= 5) {
            return ApiResponse::error('Maximum of 5 emergency contacts allowed.', 422);
        }

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string', 'max:500'],
            'relationship' => ['nullable', 'string', 'max:100'],
        ]);

        $contact = $patient->emergencyContacts()->create($data);

        return ApiResponse::created($contact, 'Emergency contact added.');
    }

    public function removeEmergencyContact(Request $request, int $id): JsonResponse
    {
        $patient = $request->user()->patient;
        $contact = $patient->emergencyContacts()->findOrFail($id);
        $contact->delete();

        return ApiResponse::success(null, 'Emergency contact removed.');
    }

    public function updateMedicalInfo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'genotype'   => ['sometimes', 'in:SS,SC,SB+,SB0,SD,SE,SO,other'],
            'blood_type' => ['sometimes', 'nullable', 'string', 'max:10'],
            'condition'  => ['sometimes', 'nullable', 'array'],
            'condition.*' => ['string', 'max:100'],
        ]);

        $request->user()->patient->update($data);

        return ApiResponse::success(null, 'Medical info updated.');
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();

        // Delete old avatar from R2 if it exists
        if ($user->avatar) {
            $oldPath = ltrim(parse_url($user->avatar, PHP_URL_PATH), '/');
            Storage::disk('r2')->delete($oldPath);
        }

        $file      = $request->file('avatar');
        $extension = $file->getClientOriginalExtension();
        $path      = 'avatars/' . $user->id . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('r2')->put($path, file_get_contents($file), 'public');

        $url = rtrim(config('filesystems.disks.r2.url'), '/') . '/' . $path;

        $user->update(['avatar' => $url]);

        return ApiResponse::success($url, 'Avatar uploaded successfully.');
    }

    public function updateFcmToken(Request $request, NotificationService $notificationService): JsonResponse
    {
        $request->validate(['fcm_token' => ['required', 'string']]);

        $notificationService->updateFcmToken($request->user(), $request->fcm_token);

        return ApiResponse::success(null, 'FCM token updated.');
    }

    public function careTeam(Request $request): JsonResponse
    {
        $patient = $request->user()->patient->load('assignedDoctor.doctor', 'assignedNurse');
        return ApiResponse::success($this->buildCareTeam($patient));
    }

    private function buildCareTeam(\App\Models\Patient $patient): array
    {
        $members = [];

        if ($patient->assigned_doctor_id && $patient->assignedDoctor) {
            $doctor    = $patient->assignedDoctor;
            $members[] = [
                'role'           => 'doctor',
                'id'             => $doctor->id,
                'name'           => $doctor->full_name,
                'avatar'         => $doctor->avatar,
                'specialisation' => $doctor->doctor?->specialisation,
                'hospital'       => $doctor->doctor?->hospital_name,
            ];
        }

        if ($patient->assigned_nurse_id && $patient->assignedNurse) {
            $nurse     = $patient->assignedNurse;
            $members[] = [
                'role'   => 'nurse',
                'id'     => $nurse->id,
                'name'   => $nurse->full_name,
                'avatar' => $nurse->avatar,
            ];
        }

        return $members;
    }
}
