<?php

use App\Jobs\CleanupBackupsJob;
use App\Jobs\CreateBackupJob;
use App\Models\BackupSettings;
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
Schedule::job(new CreateBackupJob)->when(function () {
    $settings = BackupSettings::getInstance();

    return $settings->schedule_enabled
        && $settings->schedule_frequency === 'custom'
        && ! empty($settings->cron_expression);
})->cron(function () {
    $settings = BackupSettings::getInstance();

    return $settings->cron_expression ?? '0 2 * * *'; // Default to 2 AM daily
})->name('scheduled-backup-custom');

// Schedule backup cleanup (retention policy) - runs daily at 3 AM
Schedule::job(new CleanupBackupsJob)->dailyAt('03:00')->name('backup-cleanup');
