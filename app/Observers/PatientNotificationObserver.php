<?php

namespace App\Observers;

use App\Models\PatientNotification;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class PatientNotificationObserver
{
    public function created(PatientNotification $notification): void
    {
        $patient = $notification->patient;
        $user    = $patient?->user;

        if (!$user?->fcm_token) {
            return;
        }

        // Respect the patient's push notification preference
        $settings = $patient->settings;
        if ($settings && !$settings->push_notifications_enabled) {
            return;
        }

        try {
            (new FcmService())->send(
                $user->fcm_token,
                [
                    'title' => $notification->title,
                    'body'  => $notification->body ?? '',
                ],
                array_filter([
                    'notification_id' => (string) $notification->id,
                    'type'            => $notification->type,
                ])
            );
        } catch (\Throwable $e) {
            Log::error('FCM push failed for PatientNotification', [
                'notification_id' => $notification->id,
                'patient_id'      => $notification->patient_id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
