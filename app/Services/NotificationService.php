<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function sendAlertToPatient(Alert $alert): void
    {
        $user = $alert->patient->user;
        if (!$user?->fcm_token) {
            return;
        }

        $this->sendFcm($user->fcm_token, [
            'title' => $this->alertTitle($alert),
            'body'  => $alert->message,
        ], [
            'alert_id' => (string) $alert->id,
            'type'     => $alert->type,
            'level'    => (string) $alert->level,
        ]);
    }

    public function sendAlertToDoctor(Alert $alert): void
    {
        $doctor = $alert->assignedDoctor;
        if (!$doctor?->fcm_token) {
            return;
        }

        $patientName = $alert->patient->user->name ?? 'A patient';

        $this->sendFcm($doctor->fcm_token, [
            'title' => "Patient Alert — {$patientName}",
            'body'  => $alert->message,
        ], [
            'alert_id'   => (string) $alert->id,
            'patient_id' => (string) $alert->patient_id,
        ]);
    }

    public function updateFcmToken(User $user, string $token): void
    {
        $user->update(['fcm_token' => $token]);
    }

    private function sendFcm(string $token, array $notification, array $data = []): void
    {
        // FCM HTTP v1 API — uses kutia/laravel-fcm under the hood
        // This is a stub; once FCM credentials are configured in .env,
        // replace with the actual FCM library call.
        try {
            $client = app('fcm');
            $client->send($token, $notification, $data);
        } catch (\Throwable $e) {
            Log::error('FCM send failed', ['token' => substr($token, 0, 10), 'error' => $e->getMessage()]);
        }
    }

    private function alertTitle(Alert $alert): string
    {
        return $alert->level === 1 ? '🚨 Critical Health Alert' : '⚠️ Health Warning';
    }
}
