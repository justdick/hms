<?php

use App\Jobs\CleanupBackupsJob;
use App\Jobs\CreateBackupJob;
use App\Models\BackupSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;



// Schedule auto-cancellation of old incomplete check-ins
// DISABLED: We want to keep old check-ins for historical purposes
// Schedule::command('checkins:cancel-old')->dailyAt('00:30');

// Schedule auto-completion of consultations older than 24 hours
Schedule::command('consultations:auto-complete')->hourly();

// Generate daily ward charges at midnight
Schedule::command('admissions:generate-daily-charges')->dailyAt('00:01');

// Check for due and overdue vitals every minute
Schedule::command('vitals:check-due')->everyMinute();

// Schedule database backups based on settings
Schedule::call(function () {
    $settings = BackupSettings::getInstance();

    if (! $settings->schedule_enabled) {
        return;
    }

    CreateBackupJob::dispatch();
})->when(function () {
    $settings = BackupSettings::getInstance();

    if (! $settings->schedule_enabled) {
        return false;
    }

    // Check if it's time to run based on schedule settings
    $now = now();
    $scheduleTime = $settings->schedule_time;

    // Parse schedule time (HH:MM:SS format)
    [$hour, $minute] = explode(':', $scheduleTime);

    // Check if current time matches schedule time (within the same minute)
    if ($now->hour !== (int) $hour || $now->minute !== (int) $minute) {
        return false;
    }

    switch ($settings->schedule_frequency) {
        case 'daily':
            return true;

        case 'weekly':
            // Run on Sundays (day 0)
            return $now->dayOfWeek === 0;

        case 'custom':
            // For custom cron, we'll handle it separately
            return false;

        default:
            return false;
    }
})->everyMinute()->name('scheduled-backup');

// Handle custom cron expression for backups
// Runs every minute but only executes if custom cron matches
Schedule::call(function () {
    $settings = BackupSettings::getInstance();

    if (! $settings->schedule_enabled || $settings->schedule_frequency !== 'custom') {
        return;
    }

    CreateBackupJob::dispatch();
})->when(function () {
    $settings = BackupSettings::getInstance();

    if (! $settings->schedule_enabled || $settings->schedule_frequency !== 'custom') {
        return false;
    }

    $cronExpression = $settings->cron_expression ?? '0 2 * * *';

    // Check if current time matches the cron expression
    try {
        $cron = new \Cron\CronExpression($cronExpression);

        return $cron->isDue();
    } catch (\Exception $e) {
        return false;
    }
})->everyMinute()->name('scheduled-backup-custom');

// Schedule backup cleanup (retention policy) - runs daily at 3 AM
Schedule::job(new CleanupBackupsJob)->dailyAt('03:00')->name('backup-cleanup');
