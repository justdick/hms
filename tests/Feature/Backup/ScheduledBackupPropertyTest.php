<?php

/**
 * Property-Based Tests for Scheduled Backup Jobs
 *
 * These tests verify the correctness properties of the scheduled backup
 * functionality as defined in the design document.
 */

use App\Jobs\CleanupBackupsJob;
use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\BackupSettings;
use App\Notifications\BackupFailedNotification;
use App\Services\BackupService;
use App\Services\RetentionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Use fake storage for tests
    Storage::fake('local');

    // Create the backups directory
    Storage::disk('local')->makeDirectory('backups');
});

/**
 * Property 5: Scheduled Backup Retry Behavior
 *
 * **Feature: database-backup, Property 5: Scheduled Backup Retry Behavior**
 * **Validates: Requirements 2.3**
 *
 * For any scheduled backup job that fails, the job SHALL be retried
 * up to 3 times before being marked as permanently failed.
 */
describe('Property 5: Scheduled Backup Retry Behavior', function () {
    it('has correct retry configuration', function () {
        // Arrange
        $job = new CreateBackupJob;

        // Assert - Job should have correct retry settings
        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe(60);
    });

    it('creates backup successfully on first attempt', function () {
        // Arrange
        $job = new CreateBackupJob;
        $backupService = new BackupService;

        // Act
        $job->handle($backupService);

        // Assert - Backup should be created
        expect(Backup::count())->toBe(1);
        $backup = Backup::first();
        expect($backup->status)->toBe('completed');
        expect($backup->source)->toBe('scheduled');
    });

    it('sends notification on job failure', function () {
        // Arrange
        Notification::fake();

        // Create backup settings with notification emails
        BackupSettings::create([
            'schedule_enabled' => true,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 7,
            'retention_weekly' => 4,
            'retention_monthly' => 3,
            'google_drive_enabled' => false,
            'notification_emails' => ['admin@example.com', 'backup@example.com'],
        ]);

        $job = new CreateBackupJob;
        $exception = new \RuntimeException('Test backup failure');

        // Act
        $job->failed($exception);

        // Assert - Notification should be sent
        Notification::assertSentOnDemand(
            BackupFailedNotification::class,
            function ($notification, $channels, $notifiable) {
                return in_array('admin@example.com', $notifiable->routes['mail'])
                    && in_array('backup@example.com', $notifiable->routes['mail']);
            }
        );
    });

    it('implements ShouldQueue interface', function () {
        // Arrange
        $job = new CreateBackupJob;

        // Assert - Job should implement ShouldQueue
        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('can be dispatched to queue', function () {
        // Arrange
        Queue::fake();

        // Act
        CreateBackupJob::dispatch();

        // Assert
        Queue::assertPushed(CreateBackupJob::class);
    });

    it('creates scheduled backup with null user', function () {
        // Arrange
        $job = new CreateBackupJob;
        $backupService = new BackupService;

        // Act
        $job->handle($backupService);

        // Assert - Backup should have no user
        $backup = Backup::first();
        expect($backup->created_by)->toBeNull();
        expect($backup->source)->toBe('scheduled');
    });
});

/**
 * Additional tests for CleanupBackupsJob
 */
describe('CleanupBackupsJob', function () {
    it('has correct retry configuration', function () {
        // Arrange
        $job = new CleanupBackupsJob;

        // Assert
        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe(60);
    });

    it('implements ShouldQueue interface', function () {
        // Arrange
        $job = new CleanupBackupsJob;

        // Assert
        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('can be dispatched to queue', function () {
        // Arrange
        Queue::fake();

        // Act
        CleanupBackupsJob::dispatch();

        // Assert
        Queue::assertPushed(CleanupBackupsJob::class);
    });

    it('executes retention policy when run', function () {
        // Arrange
        BackupSettings::create([
            'schedule_enabled' => true,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 2,
            'retention_weekly' => 1,
            'retention_monthly' => 1,
            'google_drive_enabled' => false,
        ]);

        // Create some backups
        $backupService = new BackupService;
        for ($i = 0; $i < 5; $i++) {
            $backup = $backupService->createBackup('scheduled');
            // Adjust created_at to simulate different days
            $backup->created_at = now()->subDays($i);
            $backup->save();
            usleep(100000); // Small delay to ensure different filenames
        }

        expect(Backup::count())->toBe(5);

        // Act
        $job = new CleanupBackupsJob;
        $retentionService = app(RetentionService::class);
        $job->handle($retentionService);

        // Assert - Some backups should be deleted based on retention policy
        // With retention_daily=2, retention_weekly=1, retention_monthly=1
        // We should keep at most 4 backups (2 daily + 1 weekly + 1 monthly, with overlap)
        expect(Backup::count())->toBeLessThanOrEqual(4);
    });
});
