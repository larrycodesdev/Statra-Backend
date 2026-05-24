<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAlertNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(public readonly int $alertId) {}

    public function handle(NotificationService $notificationService): void
    {
        $alert = Alert::with(['patient.user', 'assignedDoctor'])->findOrFail($this->alertId);

        $notificationService->sendAlertToPatient($alert);

        if ($alert->assigned_to) {
            $notificationService->sendAlertToDoctor($alert);
        }
    }
}
