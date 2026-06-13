<?php

namespace App\Console\Commands\CheckIn;

use App\Mail\CheckIn\DailyReminderMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyReminders extends Command
{
    protected $signature   = 'checkin:send-daily-reminders';
    protected $description = 'Email daily check-in reminders to check-in users who have not checked in today';

    public function handle(): int
    {
        $today = now()->toDateString();

        // Only users with an email who haven't submitted a check-in today
        $users = User::where('role', 'checkin_user')
            ->whereNotNull('email')
            ->whereDoesntHave('checkIns', fn ($q) => $q->whereDate('checked_in_at', $today))
            ->get();

        if ($users->isEmpty()) {
            $this->info('No reminders to send today.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            Mail::to($user->email)->queue(new DailyReminderMail($user->name));
        }

        $this->info("Queued daily reminders for {$users->count()} user(s).");
        return self::SUCCESS;
    }
}
