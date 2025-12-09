<?php

/**
 * Property-Based Tests for Backup Audit Logging
 *
 * **Feature: database-backup, Property 18: Audit Log Completeness**
 * **Validates: Requirements 8.4**
 *
 * Property: For any backup or restore operation performed by an authenticated user,
 * an audit log entry SHALL be created containing the user ID, action type, backup ID,
 * and timestamp.
 */

use App\Models\Backup;
use App\Models\BackupLog;
use App\Models\User;
use App\Services\BackupAuditService;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Property 18: Audit Log Completeness
 *
 * For any backup or restore operation performed by an authenticated user,
 * an audit log entry SHALL be created containing the user ID, action type,
 * backup ID, and timestamp.
 */
describe('Property 18: Audit Log Completeness', function () {
    dataset('backup_sources', [
        'manual_ui' => ['manual_ui'],
        'manual_cli' => ['manual_cli'],
        'scheduled' => ['scheduled'],
        'pre_restore' => ['pre_restore'],
    ]);

    it('creates audit log entry for backup creation with user', function (string $source) {
        // Arrange
        $user = User::factory()->create();
        $service = app(BackupService::class);

        // Act
        $backup = $service->createBackup($source, $user);

        // Assert - Audit log should be created
        $log = BackupLog::where('backup_id', $backup->id)
            ->where('action', 'created')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($user->id)
            ->and($log->backup_id)->toBe($backup->id)
            ->and($log->action)->toBe('created')
            ->and($log->details)->toContain($backup->filename)
            ->and($log->created_at)->not->toBeNull();
    })->with('backup_sources');

    it('creates audit log entry for backup creation without user (scheduled)', function () {
        // Arrange
        $service = app(BackupService::class);

        // Act
        $backup = $service->createBackup('scheduled', null);

        // Assert - Audit log should be created with null user
        $log = BackupLog::where('backup_id', $backup->id)
            ->where('action', 'created')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBeNull()
            ->and($log->backup_id)->toBe($backup->id)
            ->and($log->action)->toBe('created');
    });

    it('creates audit log entry for backup deletion', function () {
        // Arrange
        $user = User::factory()->create();
        $service = app(BackupService::class);

        // Create a backup first
        $backup = $service->createBackup('manual_ui', $user);
        $backupId = $backup->id;
        $filename = $backup->filename;

        // Clear logs from creation
        BackupLog::where('backup_id', $backupId)->delete();

        // Act
        $service->deleteBackup($backup, $user);

        // Assert - Audit log should be created for deletion
        // Note: backup_id may be null after deletion due to nullOnDelete constraint
        $log = BackupLog::where('action', 'deleted')
            ->where('user_id', $user->id)
            ->where('details', 'like', "%{$filename}%")
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($user->id)
            ->and($log->action)->toBe('deleted')
            ->and($log->details)->toContain($filename);
    });

    it('creates audit log entries for restore operation', function () {
        // Arrange
        $user = User::factory()->create();
        $auditService = app(BackupAuditService::class);
        $backup = Backup::factory()->completed()->create();
        $preRestoreBackup = Backup::factory()->completed()->create([
            'source' => 'pre_restore',
        ]);

        // Act - Test audit service directly (actual restore would wipe test database)
        $auditService->logRestoreStarted($backup, $user);
        $auditService->logPreRestoreBackupCreated($backup, $preRestoreBackup, $user);
        $auditService->logRestoreCompleted($backup, $user);

        // Assert - Multiple audit logs should be created
        $logs = BackupLog::where('backup_id', $backup->id)->get();

        // Should have: restore_started, pre_restore_backup_created, restore_completed
        expect($logs->where('action', 'restore_started')->count())->toBe(1)
            ->and($logs->where('action', 'pre_restore_backup_created')->count())->toBe(1)
            ->and($logs->where('action', 'restore_completed')->count())->toBe(1);

        // All logs should have the user ID
        foreach ($logs as $log) {
            expect($log->user_id)->toBe($user->id)
                ->and($log->created_at)->not->toBeNull();
        }
    });

    it('creates audit log entry for settings change', function () {
        // Arrange
        $user = User::factory()->create();
        $auditService = app(BackupAuditService::class);

        $changes = [
            'schedule_enabled' => true,
            'retention_daily' => 7,
        ];

        // Act
        $log = $auditService->logSettingsChanged($changes, $user);

        // Assert
        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($user->id)
            ->and($log->backup_id)->toBeNull()
            ->and($log->action)->toBe('settings_changed')
            ->and($log->details)->toContain('schedule_enabled')
            ->and($log->details)->toContain('retention_daily');
    });

    it('creates audit log entry for retention cleanup', function () {
        // Arrange
        $user = User::factory()->create();
        $auditService = app(BackupAuditService::class);

        // Act
        $log = $auditService->logRetentionCleanup(5, $user);

        // Assert
        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($user->id)
            ->and($log->backup_id)->toBeNull()
            ->and($log->action)->toBe('retention_cleanup')
            ->and($log->details)->toContain('5');
    });

    it('creates audit log entry for backup download', function () {
        // Arrange
        $user = User::factory()->create();
        $auditService = app(BackupAuditService::class);
        $backup = Backup::factory()->completed()->create();

        // Act
        $log = $auditService->logDownloaded($backup, $user);

        // Assert
        expect($log)->not->toBeNull()
            ->and($log->user_id)->toBe($user->id)
            ->and($log->backup_id)->toBe($backup->id)
            ->and($log->action)->toBe('downloaded')
            ->and($log->details)->toContain($backup->filename);
    });

    dataset('user_types', [
        'admin user' => [fn () => User::factory()->create(['name' => 'Admin User'])],
        'regular user' => [fn () => User::factory()->create(['name' => 'Regular User'])],
    ]);

    it('records correct user ID for any user type', function (callable $userFactory) {
        // Arrange
        $user = $userFactory();
        $service = app(BackupService::class);

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert
        $log = BackupLog::where('backup_id', $backup->id)
            ->where('action', 'created')
            ->first();

        expect($log->user_id)->toBe($user->id);
    })->with('user_types');

    it('records timestamp for all audit log entries', function () {
        // Arrange
        $user = User::factory()->create();
        $service = app(BackupService::class);
        $beforeTime = now()->subSecond();

        // Act
        $backup = $service->createBackup('manual_ui', $user);
        $afterTime = now()->addSecond();

        // Assert
        $log = BackupLog::where('backup_id', $backup->id)
            ->where('action', 'created')
            ->first();

        expect($log->created_at)->toBeGreaterThanOrEqual($beforeTime)
            ->and($log->created_at)->toBeLessThanOrEqual($afterTime);
    });

    it('includes meaningful details in audit log entries', function () {
        // Arrange
        $user = User::factory()->create();
        $service = app(BackupService::class);

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert
        $log = BackupLog::where('backup_id', $backup->id)
            ->where('action', 'created')
            ->first();

        // Details should include filename, source, and file size
        expect($log->details)->toContain($backup->filename)
            ->and($log->details)->toContain('manual_ui')
            ->and($log->details)->toContain('size:');
    });

    it('creates separate audit log entries for each operation', function () {
        // Arrange
        $user = User::factory()->create();
        $service = app(BackupService::class);

        // Act - Create multiple backups
        $backup1 = $service->createBackup('manual_ui', $user);
        $backup2 = $service->createBackup('manual_cli', $user);

        // Assert - Each backup should have its own audit log
        $log1 = BackupLog::where('backup_id', $backup1->id)
            ->where('action', 'created')
            ->first();
        $log2 = BackupLog::where('backup_id', $backup2->id)
            ->where('action', 'created')
            ->first();

        expect($log1)->not->toBeNull()
            ->and($log2)->not->toBeNull()
            ->and($log1->id)->not->toBe($log2->id)
            ->and($log1->details)->toContain($backup1->filename)
            ->and($log2->details)->toContain($backup2->filename);
    });
});
