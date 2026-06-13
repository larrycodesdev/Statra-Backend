<?php

use App\Console\Commands\CheckIn\SendDailyReminders;
use App\Console\Commands\CheckIn\SendWeeklySummary;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily reminder at 08:00 — only sends to users who haven't checked in today
Schedule::command(SendDailyReminders::class)->dailyAt('08:00')->withoutOverlapping();

// Weekly summary every Sunday at 08:00 — covers the past 7 days
Schedule::command(SendWeeklySummary::class)->weeklyOn(0, '08:00')->withoutOverlapping();
