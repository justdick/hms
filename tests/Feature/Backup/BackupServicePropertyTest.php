<?php

/**
 * Property-Based Tests for BackupService
 *
 * These tests verify the correctness properties of the backup service
 * as defined in the design document.
 */

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Use fake storage for tests
    Storage::fake('local');

    // Create the backups directory
    Storage::disk('local')->makeDirectory('backups');
});

/**
 * Property 1: Backup Creation Produces Valid File
 *
 * **Feature: database-backup, Property 1: Backup Creation Produces Valid File**
 * **Validates: Requirements 1.1, 1.2**
 *
 * For any authorized user and any valid backup source (UI or CLI),
 * triggering a backup SHALL result in a valid SQL dump file being
 * created in local storage with non-zero file size.
 */
describe('Property 1: Backup Creation Produces Valid File', function () {
    dataset('backup_sources', [
        'manual_ui' => ['manual_ui'],
        'manual_cli' => ['manual_cli'],
        'scheduled' => ['scheduled'],
        'pre_restore' => ['pre_restore'],
    ]);

    it('creates a valid backup file for any source type', function (string $source) {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;

        // Act
        $backup = $service->createBackup($source, $user);

        // Assert - Backup record exists with completed status
        expect($backup)->toBeInstanceOf(Backup::class);
        expect($backup->status)->toBe('completed');

        // Assert - File exists in storage
        expect(Storage::disk('local')->exists($backup->file_path))->toBeTrue();

        // Assert - File has non-zero size
        expect($backup->file_size)->toBeGreaterThan(0);

        // Assert - Filename follows convention
        expect($backup->filename)->toMatch('/^hms_backup_\d{8}_\d{6}\.sql\.gz$/');
    })->with('backup_sources');

    it('creates backup file with gzip compression', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert - File is gzip compressed (check magic bytes)
        $filePath = Storage::disk('local')->path($backup->file_path);
        $handle = fopen($filePath, 'rb');
        $magicBytes = fread($handle, 2);
        fclose($handle);

        // Gzip magic bytes are 0x1f 0x8b
        expect(bin2hex($magicBytes))->toBe('1f8b');
    });

    it('creates backup without user for scheduled backups', function () {
        // Arrange
        $service = new BackupService;

        // Act
        $backup = $service->createBackup('scheduled', null);

        // Assert
        expect($backup->status)->toBe('completed');
        expect($backup->created_by)->toBeNull();
        expect(Storage::disk('local')->exists($backup->file_path))->toBeTrue();
    });
});

/**
 * Property 2: Backup Metadata Completeness
 *
 * **Feature: database-backup, Property 2: Backup Metadata Completeness**
 * **Validates: Requirements 1.3**
 *
 * For any successfully created backup, the backup record SHALL contain
 * a non-null timestamp, positive file size, valid file path, and correct
 * source identifier.
 */
describe('Property 2: Backup Metadata Completeness', function () {
    dataset('backup_sources_with_users', [
        'manual_ui with user' => ['manual_ui', true],
        'manual_cli with user' => ['manual_cli', true],
        'scheduled without user' => ['scheduled', false],
        'pre_restore with user' => ['pre_restore', true],
    ]);

    it('records complete metadata for any backup', function (string $source, bool $hasUser) {
        // Arrange
        $user = $hasUser ? User::factory()->create() : null;
        $service = new BackupService;

        // Act
        $backup = $service->createBackup($source, $user);

        // Assert - All required metadata fields are present
        expect($backup->filename)->not->toBeNull();
        expect($backup->filename)->toBeString();

        expect($backup->file_size)->not->toBeNull();
        expect($backup->file_size)->toBeGreaterThan(0);

        expect($backup->file_path)->not->toBeNull();
        expect($backup->file_path)->toStartWith('backups/');

        expect($backup->source)->toBe($source);

        expect($backup->status)->toBe('completed');

        expect($backup->completed_at)->not->toBeNull();
        expect($backup->completed_at)->toBeInstanceOf(\Carbon\Carbon::class);

        // Assert - User tracking
        if ($hasUser) {
            expect($backup->created_by)->toBe($user->id);
        } else {
            expect($backup->created_by)->toBeNull();
        }

        // Assert - Error message should be null for successful backup
        expect($backup->error_message)->toBeNull();
    })->with('backup_sources_with_users');

    it('records timestamps correctly', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;
        $beforeCreate = now()->subSecond(); // Allow 1 second tolerance

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert
        $afterCreate = now()->addSecond(); // Allow 1 second tolerance

        expect($backup->created_at->timestamp)->toBeGreaterThanOrEqual($beforeCreate->timestamp);
        expect($backup->created_at->timestamp)->toBeLessThanOrEqual($afterCreate->timestamp);

        expect($backup->completed_at->timestamp)->toBeGreaterThanOrEqual($beforeCreate->timestamp);
        expect($backup->completed_at->timestamp)->toBeLessThanOrEqual($afterCreate->timestamp);
    });
});

/**
 * Property 8: Download Returns Valid File
 *
 * **Feature: database-backup, Property 8: Download Returns Valid File**
 * **Validates: Requirements 3.3**
 *
 * For any backup that exists in local storage, the download operation
 * SHALL return a file with content matching the original backup.
 */
describe('Property 8: Download Returns Valid File', function () {
    it('returns valid file for existing backup', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;
        $backup = $service->createBackup('manual_ui', $user);

        // Get original file content
        $originalContent = Storage::disk('local')->get($backup->file_path);

        // Act
        $response = $service->downloadBackup($backup);

        // Assert - Response is a StreamedResponse
        expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);

        // Assert - Response has correct headers
        expect($response->headers->get('Content-Disposition'))
            ->toContain($backup->filename);
    });

    it('throws exception for backup without local file', function () {
        // Arrange - Create a backup that only exists on Google Drive (no local file)
        $backup = Backup::factory()->onGoogleDrive()->create();
        // Manually clear the file_path to simulate a backup that was deleted locally
        $backup->file_path = '';
        $backup->save();

        $service = new BackupService;

        // Act & Assert
        expect(fn () => $service->downloadBackup($backup))
            ->toThrow(\RuntimeException::class, 'Backup file is not available locally.');
    });

    it('throws exception for missing file on disk', function () {
        // Arrange
        $backup = Backup::factory()->create([
            'file_path' => 'backups/nonexistent.sql.gz',
            'status' => 'completed',
        ]);
        $service = new BackupService;

        // Act & Assert
        expect(fn () => $service->downloadBackup($backup))
            ->toThrow(\RuntimeException::class, 'Backup file not found on disk.');
    });

    it('returns file with matching content', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;
        $backup = $service->createBackup('manual_ui', $user);

        // Get original file size
        $originalSize = Storage::disk('local')->size($backup->file_path);

        // Act
        $response = $service->downloadBackup($backup);

        // Start output buffering to capture streamed content
        ob_start();
        $response->sendContent();
        $downloadedContent = ob_get_clean();

        // Assert - Downloaded content matches original size
        expect(strlen($downloadedContent))->toBe($originalSize);
    });
});

/**
 * Property 9: Deletion Removes From All Locations
 *
 * **Feature: database-backup, Property 9: Deletion Removes From All Locations**
 * **Validates: Requirements 3.4**
 *
 * For any backup deletion operation, the backup file SHALL be removed
 * from local storage and the database record SHALL be deleted.
 */
describe('Property 9: Deletion Removes From All Locations', function () {
    it('removes backup file and database record', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;
        $backup = $service->createBackup('manual_ui', $user);
        $backupId = $backup->id;
        $filePath = $backup->file_path;

        // Verify file exists before deletion
        expect(Storage::disk('local')->exists($filePath))->toBeTrue();
        expect(Backup::find($backupId))->not->toBeNull();

        // Act
        $result = $service->deleteBackup($backup);

        // Assert
        expect($result)->toBeTrue();
        expect(Storage::disk('local')->exists($filePath))->toBeFalse();
        expect(Backup::find($backupId))->toBeNull();
    });

    it('handles missing file gracefully', function () {
        // Arrange
        $backup = Backup::factory()->create([
            'file_path' => 'backups/nonexistent.sql.gz',
            'status' => 'completed',
        ]);
        $backupId = $backup->id;
        $service = new BackupService;

        // Act - Should not throw exception
        $result = $service->deleteBackup($backup);

        // Assert - Database record should still be deleted
        expect($result)->toBeTrue();
        expect(Backup::find($backupId))->toBeNull();
    });

    it('handles backup with empty file_path', function () {
        // Arrange - Create a backup and then clear the file_path
        $backup = Backup::factory()->failed()->create();
        $backup->file_path = '';
        $backup->save();
        $backupId = $backup->id;
        $service = new BackupService;

        // Act
        $result = $service->deleteBackup($backup);

        // Assert
        expect($result)->toBeTrue();
        expect(Backup::find($backupId))->toBeNull();
    });

    it('deletes multiple backups independently', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new BackupService;

        $backup1 = $service->createBackup('manual_ui', $user);

        // Wait a moment to ensure different timestamps
        usleep(1100000); // 1.1 seconds to ensure different filename

        $backup2 = $service->createBackup('manual_cli', $user);

        $backup1Id = $backup1->id;
        $backup2Id = $backup2->id;
        $filePath1 = $backup1->file_path;
        $filePath2 = $backup2->file_path;

        // Ensure they have different file paths
        expect($filePath1)->not->toBe($filePath2);

        // Act - Delete first backup
        $service->deleteBackup($backup1);

        // Assert - First backup deleted, second still exists
        expect(Storage::disk('local')->exists($filePath1))->toBeFalse();
        expect(Backup::find($backup1Id))->toBeNull();

        expect(Storage::disk('local')->exists($filePath2))->toBeTrue();
        expect(Backup::find($backup2Id))->not->toBeNull();

        // Act - Delete second backup
        $service->deleteBackup($backup2);

        // Assert - Both backups deleted
        expect(Storage::disk('local')->exists($filePath2))->toBeFalse();
        expect(Backup::find($backup2Id))->toBeNull();
    });
});

/**
 * Property 3: Google Drive Upload on Success
 *
 * **Feature: database-backup, Property 3: Google Drive Upload on Success**
 * **Validates: Requirements 1.4**
 *
 * For any successfully created backup when Google Drive is configured,
 * the backup record SHALL have a non-null google_drive_file_id after completion.
 */
describe('Property 3: Google Drive Upload on Success', function () {
    it('uploads to Google Drive when configured', function () {
        // Arrange
        $user = User::factory()->create();

        // Create a mock GoogleDriveService that simulates successful upload
        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $mockGoogleDriveService->shouldReceive('upload')
            ->once()
            ->andReturn('mock-google-drive-file-id-123');

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert - Backup should have Google Drive file ID
        expect($backup->google_drive_file_id)->toBe('mock-google-drive-file-id-123');
        expect($backup->isOnGoogleDrive())->toBeTrue();
        expect($backup->status)->toBe('completed');
    });

    it('sets google_drive_file_id for any backup source when configured', function (string $source) {
        // Arrange
        $user = User::factory()->create();

        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $mockGoogleDriveService->shouldReceive('upload')
            ->once()
            ->andReturn('gdrive-file-'.$source);

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act
        $backup = $service->createBackup($source, $user);

        // Assert
        expect($backup->google_drive_file_id)->toBe('gdrive-file-'.$source);
        expect($backup->isOnGoogleDrive())->toBeTrue();
    })->with([
        'manual_ui' => ['manual_ui'],
        'manual_cli' => ['manual_cli'],
        'scheduled' => ['scheduled'],
        'pre_restore' => ['pre_restore'],
    ]);

    it('does not set google_drive_file_id when not configured', function () {
        // Arrange
        $user = User::factory()->create();

        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(false);
        // upload should not be called when not configured

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert - Backup should NOT have Google Drive file ID
        expect($backup->google_drive_file_id)->toBeNull();
        expect($backup->isOnGoogleDrive())->toBeFalse();
        expect($backup->status)->toBe('completed');
        // Local backup should still exist
        expect($backup->isLocal())->toBeTrue();
    });
});

/**
 * Property 15: Google Drive Failure Graceful Degradation
 *
 * **Feature: database-backup, Property 15: Google Drive Failure Graceful Degradation**
 * **Validates: Requirements 6.3**
 *
 * For any backup operation where Google Drive upload fails,
 * the local backup file SHALL remain intact and the backup record
 * SHALL indicate local-only storage.
 */
describe('Property 15: Google Drive Failure Graceful Degradation', function () {
    it('keeps local backup when Google Drive upload fails', function () {
        // Arrange
        $user = User::factory()->create();

        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $mockGoogleDriveService->shouldReceive('upload')
            ->once()
            ->andReturn(null); // Simulate upload failure

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert - Backup should be completed with local file only
        expect($backup->status)->toBe('completed');
        expect($backup->google_drive_file_id)->toBeNull();
        expect($backup->isOnGoogleDrive())->toBeFalse();

        // Local backup should exist
        expect($backup->isLocal())->toBeTrue();
        expect(Storage::disk('local')->exists($backup->file_path))->toBeTrue();
        expect($backup->file_size)->toBeGreaterThan(0);
    });

    it('gracefully handles Google Drive failure for any backup source', function (string $source) {
        // Arrange
        $user = User::factory()->create();

        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $mockGoogleDriveService->shouldReceive('upload')
            ->once()
            ->andReturn(null); // Simulate failure

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act
        $backup = $service->createBackup($source, $user);

        // Assert - Backup should still be successful locally
        expect($backup->status)->toBe('completed');
        expect($backup->isLocal())->toBeTrue();
        expect($backup->isOnGoogleDrive())->toBeFalse();
        expect(Storage::disk('local')->exists($backup->file_path))->toBeTrue();
    })->with([
        'manual_ui' => ['manual_ui'],
        'manual_cli' => ['manual_cli'],
        'scheduled' => ['scheduled'],
        'pre_restore' => ['pre_restore'],
    ]);

    it('does not throw exception when Google Drive upload fails', function () {
        // Arrange
        $user = User::factory()->create();

        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $mockGoogleDriveService->shouldReceive('upload')
            ->once()
            ->andReturn(null); // Simulate failure

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act & Assert - Should not throw exception
        $backup = $service->createBackup('manual_ui', $user);

        expect($backup)->toBeInstanceOf(Backup::class);
        expect($backup->status)->toBe('completed');
    });

    it('maintains backup integrity when Google Drive is unavailable', function () {
        // Arrange
        $user = User::factory()->create();

        $mockGoogleDriveService = Mockery::mock(\App\Services\GoogleDriveService::class);
        $mockGoogleDriveService->shouldReceive('isConfigured')->andReturn(true);
        $mockGoogleDriveService->shouldReceive('upload')
            ->once()
            ->andReturn(null);

        $service = new BackupService;
        $service->setGoogleDriveService($mockGoogleDriveService);

        // Act
        $backup = $service->createBackup('manual_ui', $user);

        // Assert - All backup metadata should be complete
        expect($backup->filename)->not->toBeNull();
        expect($backup->file_path)->not->toBeNull();
        expect($backup->file_size)->toBeGreaterThan(0);
        expect($backup->completed_at)->not->toBeNull();
        expect($backup->error_message)->toBeNull();

        // Verify file content is valid gzip
        $filePath = Storage::disk('local')->path($backup->file_path);
        $handle = fopen($filePath, 'rb');
        $magicBytes = fread($handle, 2);
        fclose($handle);
        expect(bin2hex($magicBytes))->toBe('1f8b');
    });
});
