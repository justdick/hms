<?php

/**
 * Property-Based Tests for BackupNotificationService
 *
 * These tests verify the correctness properties of the backup notification service
 * as defined in the design document.
 *
 * **Feature: database-backup, Property 4: Failure Notification Dispatch**
 * **Validates: Requirements 1.5, 7.2, 7.3, 7.4**
 */

use App\Models\Backup;
use App\Models\BackupSettings;
use App\Notifications\BackupFailedNotification;
use App\Services\BackupNotificationService;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    // Fake notifications for testing
    Notification::fake();
});

/**
 * Property 4: Failure Notification Dispatch
 *
 * **Feature: database-backup, Property 4: Failure Notification Dispatch**
 * **Validates: Requirements 1.5, 7.2, 7.3, 7.4**
 *
 * For any backup or restore operation that fails, a notification SHALL be
 * dispatched to all configured email recipients containing the error details.
 */
describe('Property 4: Failure Notification Dispatch', function () {
    dataset('error_messages', [
        'simple error' => ['Database connection failed'],
        'detailed error' => ['Failed to execute mysqldump: Access denied for user'],
        'timeout error' => ['Operation timed out after 3600 seconds'],
        'disk space error' => ['Insufficient disk space to create backup'],
        'permission error' => ['Permission denied: cannot write to backup directory'],
    ]);

    dataset('notification_emails', [
        'single recipient' => [['admin@hospital.com']],
        'multiple recipients' => [['admin@hospital.com', 'backup@hospital.com', 'it@hospital.com']],
    ]);

    it('sends backup failure notification to all configured recipients', function (array $emails, string $error) {
        // Arrange
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => $emails,
        ]);

        $backup = Backup::factory()->failed()->create([
            'error_message' => $error,
        ]);

        $service = new BackupNotificationService;

        // Act
        $service->notifyBackupFailure($backup, $error);

        // Assert - Notification sent to all recipients
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification, array $channels, object $notifiable) use ($emails, $error, $backup) {
                // Verify notification type
                if ($notification->type !== 'backup') {
                    return false;
                }

                // Verify error message
                if ($notification->error !== $error) {
                    return false;
                }

                // Verify backup reference
                if ($notification->backup->id !== $backup->id) {
                    return false;
                }

                // Verify recipients
                if ($notifiable->routes['mail'] !== $emails) {
                    return false;
                }

                return true;
            }
        );
    })->with('notification_emails')->with('error_messages');

    it('sends restore failure notification to all configured recipients', function (array $emails, string $error) {
        // Arrange
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => $emails,
        ]);

        $backup = Backup::factory()->create();

        $service = new BackupNotificationService;

        // Act
        $service->notifyRestoreFailure($backup, $error);

        // Assert - Notification sent to all recipients
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification, array $channels, object $notifiable) use ($emails, $error, $backup) {
                // Verify notification type
                if ($notification->type !== 'restore') {
                    return false;
                }

                // Verify error message
                if ($notification->error !== $error) {
                    return false;
                }

                // Verify backup reference
                if ($notification->backup->id !== $backup->id) {
                    return false;
                }

                // Verify recipients
                if ($notifiable->routes['mail'] !== $emails) {
                    return false;
                }

                return true;
            }
        );
    })->with('notification_emails')->with('error_messages');

    it('sends scheduled backup failure notification to all configured recipients', function (array $emails, string $error) {
        // Arrange
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => true,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => $emails,
        ]);

        $backup = Backup::factory()->failed()->create([
            'source' => 'scheduled',
            'error_message' => $error,
        ]);

        $service = new BackupNotificationService;

        // Act
        $service->notifyScheduledBackupFailure($error, $backup);

        // Assert - Notification sent to all recipients
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification, array $channels, object $notifiable) use ($emails, $error, $backup) {
                // Verify notification type
                if ($notification->type !== 'scheduled') {
                    return false;
                }

                // Verify error message
                if ($notification->error !== $error) {
                    return false;
                }

                // Verify backup reference (may be null for scheduled failures)
                if ($notification->backup && $notification->backup->id !== $backup->id) {
                    return false;
                }

                // Verify recipients
                if ($notifiable->routes['mail'] !== $emails) {
                    return false;
                }

                return true;
            }
        );
    })->with('notification_emails')->with('error_messages');

    it('does not send notification when no recipients configured', function () {
        // Arrange
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => [], // Empty recipients
        ]);

        $backup = Backup::factory()->failed()->create();
        $service = new BackupNotificationService;

        // Act
        $service->notifyBackupFailure($backup, 'Test error');
        $service->notifyRestoreFailure($backup, 'Test error');
        $service->notifyScheduledBackupFailure('Test error', $backup);

        // Assert - No notifications sent
        Notification::assertNothingSent();
    });

    it('includes error details in notification for any failure type', function (string $failureType) {
        // Arrange
        $emails = ['admin@hospital.com'];
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => $emails,
        ]);

        $backup = Backup::factory()->create([
            'source' => $failureType === 'scheduled' ? 'scheduled' : 'manual_ui',
        ]);
        $error = 'Detailed error message for testing';

        $service = new BackupNotificationService;

        // Act
        match ($failureType) {
            'backup' => $service->notifyBackupFailure($backup, $error),
            'restore' => $service->notifyRestoreFailure($backup, $error),
            'scheduled' => $service->notifyScheduledBackupFailure($error, $backup),
        };

        // Assert - Notification contains error details
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification) use ($error, $failureType) {
                // Verify error is included
                expect($notification->error)->toBe($error);

                // Verify type matches
                expect($notification->type)->toBe($failureType);

                // Verify timestamp is set
                expect($notification->timestamp)->not->toBeNull();

                return true;
            }
        );
    })->with([
        'backup failure' => ['backup'],
        'restore failure' => ['restore'],
        'scheduled failure' => ['scheduled'],
    ]);

    it('notification contains backup info when available', function () {
        // Arrange
        $emails = ['admin@hospital.com'];
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => $emails,
        ]);

        $backup = Backup::factory()->create([
            'filename' => 'hms_backup_20251208_120000.sql.gz',
            'source' => 'manual_ui',
            'status' => 'failed',
        ]);

        $service = new BackupNotificationService;

        // Act
        $service->notifyBackupFailure($backup, 'Test error');

        // Assert - Notification contains backup info
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification) use ($backup) {
                expect($notification->backup)->not->toBeNull();
                expect($notification->backup->id)->toBe($backup->id);
                expect($notification->backup->filename)->toBe($backup->filename);
                expect($notification->backup->source)->toBe($backup->source);

                return true;
            }
        );
    });

    it('scheduled failure notification works without backup object', function () {
        // Arrange
        $emails = ['admin@hospital.com'];
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => true,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => $emails,
        ]);

        $service = new BackupNotificationService;
        $error = 'Scheduled backup failed before creating backup record';

        // Act
        $service->notifyScheduledBackupFailure($error, null);

        // Assert - Notification sent even without backup object
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function (BackupFailedNotification $notification) use ($error) {
                expect($notification->type)->toBe('scheduled');
                expect($notification->error)->toBe($error);
                expect($notification->backup)->toBeNull();

                return true;
            }
        );
    });
});

/**
 * Test notification mail content
 */
describe('Notification Mail Content', function () {
    it('generates correct email subject for each failure type', function (string $type, string $expectedSubject) {
        // Arrange
        $backup = Backup::factory()->create();
        $notification = new BackupFailedNotification(
            type: $type,
            backup: $backup,
            error: 'Test error',
            timestamp: now()
        );

        // Act
        $mailMessage = $notification->toMail(new \stdClass);

        // Assert
        expect($mailMessage->subject)->toBe($expectedSubject);
    })->with([
        'backup' => ['backup', '[HMS] Backup Operation Failed'],
        'restore' => ['restore', '[HMS] Database Restore Failed'],
        'scheduled' => ['scheduled', '[HMS] Scheduled Backup Failed - Action Required'],
    ]);

    it('includes all required information in array representation', function () {
        // Arrange
        $backup = Backup::factory()->create([
            'filename' => 'test_backup.sql.gz',
            'source' => 'manual_ui',
        ]);
        $error = 'Test error message';
        $timestamp = now();

        $notification = new BackupFailedNotification(
            type: 'backup',
            backup: $backup,
            error: $error,
            timestamp: $timestamp
        );

        // Act
        $array = $notification->toArray(new \stdClass);

        // Assert
        expect($array)->toHaveKeys([
            'type',
            'backup_id',
            'backup_filename',
            'backup_source',
            'error',
            'timestamp',
        ]);
        expect($array['type'])->toBe('backup');
        expect($array['backup_id'])->toBe($backup->id);
        expect($array['backup_filename'])->toBe($backup->filename);
        expect($array['backup_source'])->toBe($backup->source);
        expect($array['error'])->toBe($error);
    });

    it('uses mail channel for delivery', function () {
        // Arrange
        $notification = new BackupFailedNotification(
            type: 'backup',
            backup: Backup::factory()->create(),
            error: 'Test error',
            timestamp: now()
        );

        // Act
        $channels = $notification->via(new \stdClass);

        // Assert
        expect($channels)->toBe(['mail']);
    });
});
