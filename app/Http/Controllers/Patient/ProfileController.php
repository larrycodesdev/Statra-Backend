<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user    = $request->user()->load('patient');
        $patient = $user->patient;

        return ApiResponse::success([
            'id'                      => $user->id,
            'uuid'                    => $user->uuid,
            'name'                    => $user->name,
            'email'                   => $user->email,
            'phone'                   => $user->phone,
            'avatar'                  => $user->avatar,
            'role'                    => $user->role,
            'genotype'                => $patient->genotype,
            'blood_type'              => $patient->blood_type,
            'date_of_birth'           => $patient->date_of_birth?->format('Y-m-d'),
            'gender'                  => $patient->gender,
            'emergency_contact_name'  => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'phone'  => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        $request->user()->update($data);

        return ApiResponse::success(null, 'Profile updated.');
    }

    public function updateEmergencyContacts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'emergency_contact_name'  => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
        ]);

        $request->user()->patient->update($data);

        return ApiResponse::success(null, 'Emergency contact updated.');
    }

    public function updateMedicalInfo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'genotype'      => ['sometimes', 'in:SS,SC,SB,other'],
            'blood_type'    => ['sometimes', 'nullable', 'string', 'max:10'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'gender'        => ['sometimes', 'in:male,female,other'],
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
}
