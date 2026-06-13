<?php

namespace App\Console\Commands\CheckIn;

use App\Mail\CheckIn\WeeklySummaryMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendWeeklySummary extends Command
{
    protected $signature   = 'checkin:send-weekly-summary';
    protected $description = 'Email a weekly health summary to all check-in users who have an email address';

    public function handle(): int
    {
        $weekStart = now()->subDays(6)->startOfDay();
        $weekEnd   = now()->endOfDay();

        $users = User::where('role', 'checkin_user')
            ->whereNotNull('email')
            ->with(['checkIns' => fn ($q) => $q->whereBetween('checked_in_at', [$weekStart, $weekEnd])])
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users to send weekly summary to.');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $checkIns = $user->checkIns;
            $stats    = $this->buildStats($checkIns, $weekStart, $weekEnd);

            Mail::to($user->email)->queue(new WeeklySummaryMail($user->name, $stats));
        }

        $this->info("Queued weekly summary for {$users->count()} user(s).");
        return self::SUCCESS;
    }

    private function buildStats($checkIns, $weekStart, $weekEnd): array
    {
        $total = $checkIns->count();

        $statusCounts = [
            'Stable'       => 0,
            'Watch closely' => 0,
            'Elevated'     => 0,
            'Urgent'       => 0,
        ];

        foreach ($checkIns as $ci) {
            if (isset($statusCounts[$ci->status])) {
                $statusCounts[$ci->status]++;
            }
        }

        $mostCommon = $total > 0
            ? array_key_first(array_filter($statusCounts, fn ($v) => $v === max($statusCounts)))
            : 'N/A';

        // Unique days that had at least one check-in
        $daysLogged = $checkIns
            ->map(fn ($ci) => $ci->checked_in_at->toDateString())
            ->unique()
            ->count();

        return [
            'week_range'        => $weekStart->format('M j') . ' – ' . $weekEnd->format('M j, Y'),
            'total_checkins'    => $total,
            'avg_pain'          => $total > 0 ? round($checkIns->avg('pain'), 1) : 0,
            'red_flag_count'    => $checkIns->where('red_flag', true)->count(),
            'days_logged'       => $daysLogged,
            'most_common_status' => $mostCommon,
            'status_breakdown'  => array_filter($statusCounts, fn ($v) => $v > 0),
        ];
    }
}
