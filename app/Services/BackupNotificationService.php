<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupSettings;
use App\Notifications\BackupFailedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class BackupNotificationService
{
    /**
     * Send notification when a backup operation fails.
     *
     * @param  Backup  $backup  The failed backup
     * @param  string  $error  The error message
     */
    public function notifyBackupFailure(Backup $backup, string $error): void
    {
        $recipients = $this->getNotificationRecipients();

        if (empty($recipients)) {
            Log::info('No notification recipients configured for backup failure', [
                'backup_id' => $backup->id,
                'error' => $error,
            ]);

            return;
        }

        Notification::route('mail', $recipients)
            ->notify(new BackupFailedNotification(
                type: 'backup',
                backup: $backup,
                error: $error,
                timestamp: now()
            ));

        Log::info('Backup failure notification sent', [
            'backup_id' => $backup->id,
            'recipients' => $recipients,
            'error' => $error,
        ]);
    }

    /**
     * Send notification when a restore operation fails.
     *
     * @param  Backup  $backup  The backup that failed to restore
     * @param  string  $error  The error message
     */
    public function notifyRestoreFailure(Backup $backup, string $error): void
    {
        $recipients = $this->getNotificationRecipients();

        if (empty($recipients)) {
            Log::info('No notification recipients configured for restore failure', [
                'backup_id' => $backup->id,
                'error' => $error,
            ]);

            return;
        }

        Notification::route('mail', $recipients)
            ->notify(new BackupFailedNotification(
                type: 'restore',
                backup: $backup,
                error: $error,
                timestamp: now()
            ));

        Log::info('Restore failure notification sent', [
            'backup_id' => $backup->id,
            'recipients' => $recipients,
            'error' => $error,
        ]);
    }

    /**
     * Send notification when a scheduled backup fails after all retry attempts.
     *
     * @param  string  $error  The error message
     * @param  Backup|null  $backup  The failed backup (if one was created)
     */
    public function notifyScheduledBackupFailure(string $error, ?Backup $backup = null): void
    {
        $recipients = $this->getNotificationRecipients();

        if (empty($recipients)) {
            Log::info('No notification recipients configured for scheduled backup failure', [
                'backup_id' => $backup?->id,
                'error' => $error,
            ]);

            return;
        }

        Notification::route('mail', $recipients)
            ->notify(new BackupFailedNotification(
                type: 'scheduled',
                backup: $backup,
                error: $error,
                timestamp: now()
            ));

        Log::info('Scheduled backup failure notification sent', [
            'backup_id' => $backup?->id,
            'recipients' => $recipients,
            'error' => $error,
        ]);
    }

    /**
     * Get the configured notification email recipients.
     *
     * @return array<string> The email addresses
     */
    protected function getNotificationRecipients(): array
    {
        $settings = BackupSettings::getInstance();

        return $settings->notification_emails ?? [];
    }
}
