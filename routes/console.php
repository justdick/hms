<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule auto-cancellation of old incomplete check-ins
Schedule::command('checkins:cancel-old')->dailyAt('00:30');

// Schedule auto-completion of consultations older than 24 hours
Schedule::command('consultations:auto-complete')->hourly();

// Check for due and overdue vitals every minute
Schedule::command('vitals:check-due')->everyMinute();
