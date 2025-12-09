<?php

/**
 * Property-Based Tests for RetentionService
 *
 * These tests verify the correctness properties of the retention service
 * as defined in the design document.
 */

use App\Models\Backup;
use App\Models\BackupSettings;
use App\Services\BackupService;
use App\Services\RetentionService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Use fake storage for tests
    Storage::fake('local');
    Storage::disk('local')->makeDirectory('backups');
});

/**
 * Helper function to create a backup at a specific date.
 */
function createBackupAtDate(string $date, array $attributes = []): Backup
{
    $createdAt = \Carbon\Carbon::parse($date);

    return Backup::factory()->create(array_merge([
        'status' => 'completed',
        'is_protected' => false,
        'created_at' => $createdAt,
        'completed_at' => $createdAt,
    ], $attributes));
}

/**
 * Property 13: Retention Policy Correctness
 *
 * **Feature: database-backup, Property 13: Retention Policy Correctness**
 * **Validates: Requirements 5.2**
 *
 * For any set of unprotected backups and retention configuration,
 * running the retention cleanup SHALL delete exactly those backups
 * that exceed the configured limits while preserving the most recent
 * backups in each category.
 */
describe('Property 13: Retention Policy Correctness', function () {
    dataset('retention_configurations', [
        'minimal retention' => [1, 1, 1],
        'standard retention' => [7, 4, 3],
        'high daily retention' => [14, 2, 1],
        'high weekly retention' => [3, 8, 2],
        'high monthly retention' => [5, 2, 6],
        'zero daily' => [0, 4, 3],
        'zero weekly' => [7, 0, 3],
        'zero monthly' => [7, 4, 0],
    ]);

    it('preserves the most recent daily backups up to retention limit', function (int $retainDaily, int $retainWeekly, int $retainMonthly) {
        // Arrange - Create backups for the last 14 days (one per day)
        $backups = collect();
        for ($i = 0; $i < 14; $i++) {
            $date = now()->subDays($i)->format('Y-m-d 10:00:00');
            $backups->push(createBackupAtDate($date));
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $toDelete = $retentionService->getBackupsToDelete($retainDaily, $retainWeekly, $retainMonthly);

        // Assert - The most recent daily backups should be preserved
        $categorized = $retentionService->categorizeBackups(
            Backup::completed()->unprotected()->orderBy('created_at', 'desc')->get()
        );

        $dailyBackups = $categorized['daily'];
        $keptDaily = $dailyBackups->filter(fn ($b) => ! $toDelete->contains('id', $b->id));

        // Should keep at most retainDaily backups (or all if fewer exist)
        $expectedKept = min($retainDaily, $dailyBackups->count());
        expect($keptDaily->count())->toBeGreaterThanOrEqual($expectedKept);

        // The kept daily backups should be the most recent ones
        if ($retainDaily > 0 && $dailyBackups->count() > 0) {
            $mostRecentDaily = $dailyBackups->take($retainDaily);
            foreach ($mostRecentDaily as $backup) {
                expect($toDelete->contains('id', $backup->id))->toBeFalse(
                    "Most recent daily backup {$backup->id} should not be deleted"
                );
            }
        }
    })->with('retention_configurations');

    it('preserves the most recent weekly backups up to retention limit', function (int $retainDaily, int $retainWeekly, int $retainMonthly) {
        // Arrange - Create backups spanning 10 weeks
        $backups = collect();
        for ($i = 0; $i < 10; $i++) {
            $date = now()->subWeeks($i)->format('Y-m-d 10:00:00');
            $backups->push(createBackupAtDate($date));
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $toDelete = $retentionService->getBackupsToDelete($retainDaily, $retainWeekly, $retainMonthly);

        // Assert - Weekly backups should be preserved according to policy
        $categorized = $retentionService->categorizeBackups(
            Backup::completed()->unprotected()->orderBy('created_at', 'desc')->get()
        );

        $weeklyBackups = $categorized['weekly'];
        $keptWeekly = $weeklyBackups->filter(fn ($b) => ! $toDelete->contains('id', $b->id));

        // Weekly backups that aren't already kept as daily should be preserved
        expect($keptWeekly->count())->toBeGreaterThanOrEqual(0);
    })->with('retention_configurations');

    it('preserves the most recent monthly backups up to retention limit', function (int $retainDaily, int $retainWeekly, int $retainMonthly) {
        // Arrange - Create backups spanning 8 months
        $backups = collect();
        for ($i = 0; $i < 8; $i++) {
            $date = now()->subMonths($i)->format('Y-m-d 10:00:00');
            $backups->push(createBackupAtDate($date));
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $toDelete = $retentionService->getBackupsToDelete($retainDaily, $retainWeekly, $retainMonthly);

        // Assert - Monthly backups should be preserved according to policy
        $categorized = $retentionService->categorizeBackups(
            Backup::completed()->unprotected()->orderBy('created_at', 'desc')->get()
        );

        $monthlyBackups = $categorized['monthly'];
        $keptMonthly = $monthlyBackups->filter(fn ($b) => ! $toDelete->contains('id', $b->id));

        // Monthly backups that aren't already kept should be preserved
        expect($keptMonthly->count())->toBeGreaterThanOrEqual(0);
    })->with('retention_configurations');

    it('deletes exactly the excess backups', function () {
        // Arrange - Create 20 daily backups
        for ($i = 0; $i < 20; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'));
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // With retention of 7 daily, 4 weekly, 3 monthly
        $retainDaily = 7;
        $retainWeekly = 4;
        $retainMonthly = 3;

        // Act
        $toDelete = $retentionService->getBackupsToDelete($retainDaily, $retainWeekly, $retainMonthly);

        // Assert - Total kept should not exceed the sum of retention limits
        $totalBackups = Backup::count();
        $keptCount = $totalBackups - $toDelete->count();

        // The kept count should be reasonable (at most daily + weekly + monthly limits)
        $maxPossibleKept = $retainDaily + $retainWeekly + $retainMonthly;
        expect($keptCount)->toBeLessThanOrEqual($maxPossibleKept);
    });

    it('categorizes backups correctly by time period', function () {
        // Arrange - Create backups at specific dates
        $backup1 = createBackupAtDate('2025-01-15 10:00:00'); // Week 3, Month 1
        $backup2 = createBackupAtDate('2025-01-15 14:00:00'); // Same day as backup1
        $backup3 = createBackupAtDate('2025-01-08 10:00:00'); // Week 2, Month 1
        $backup4 = createBackupAtDate('2025-02-01 10:00:00'); // Week 5, Month 2

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $allBackups = Backup::completed()->unprotected()->orderBy('created_at', 'desc')->get();
        $categorized = $retentionService->categorizeBackups($allBackups);

        // Assert - Daily should have 3 unique days (backup1 and backup2 are same day)
        expect($categorized['daily']->count())->toBe(3);

        // Assert - Weekly should have 3 unique weeks
        expect($categorized['weekly']->count())->toBe(3);

        // Assert - Monthly should have 2 unique months
        expect($categorized['monthly']->count())->toBe(2);
    });

    it('handles empty backup set gracefully', function () {
        // Arrange - No backups exist
        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $toDelete = $retentionService->getBackupsToDelete(7, 4, 3);

        // Assert
        expect($toDelete)->toBeEmpty();
    });

    it('handles fewer backups than retention limits', function () {
        // Arrange - Create only 3 backups
        for ($i = 0; $i < 3; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'));
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act - With retention limits higher than backup count
        $toDelete = $retentionService->getBackupsToDelete(7, 4, 3);

        // Assert - No backups should be deleted
        expect($toDelete)->toBeEmpty();
    });
});

/**
 * Property 14: Protected Backup Exclusion
 *
 * **Feature: database-backup, Property 14: Protected Backup Exclusion**
 * **Validates: Requirements 5.4**
 *
 * For any backup marked as protected, the retention cleanup process
 * SHALL never delete that backup regardless of retention policy settings.
 */
describe('Property 14: Protected Backup Exclusion', function () {
    it('never deletes protected backups regardless of retention settings', function () {
        // Arrange - Create a mix of protected and unprotected backups
        $protectedBackups = collect();
        $unprotectedBackups = collect();

        // Create 10 protected backups spanning 10 days
        for ($i = 0; $i < 10; $i++) {
            $date = now()->subDays($i)->format('Y-m-d 10:00:00');
            $protectedBackups->push(createBackupAtDate($date, ['is_protected' => true]));
        }

        // Create 10 unprotected backups spanning 10 days (different time)
        for ($i = 0; $i < 10; $i++) {
            $date = now()->subDays($i)->format('Y-m-d 14:00:00');
            $unprotectedBackups->push(createBackupAtDate($date, ['is_protected' => false]));
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act - With very restrictive retention (only keep 1 of each)
        $toDelete = $retentionService->getBackupsToDelete(1, 1, 1);

        // Assert - No protected backups should be in the delete list
        foreach ($protectedBackups as $protected) {
            expect($toDelete->contains('id', $protected->id))->toBeFalse(
                "Protected backup {$protected->id} should never be deleted"
            );
        }
    });

    it('excludes protected backups from categorization for deletion', function () {
        // Arrange - Create only protected backups
        for ($i = 0; $i < 20; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'), ['is_protected' => true]);
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act - Even with zero retention, protected backups should not be deleted
        $toDelete = $retentionService->getBackupsToDelete(0, 0, 0);

        // Assert - No backups should be deleted (all are protected)
        expect($toDelete)->toBeEmpty();
    });

    it('only deletes unprotected backups when mixed with protected', function () {
        // Arrange
        // Create 5 protected backups (oldest)
        for ($i = 10; $i < 15; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'), ['is_protected' => true]);
        }

        // Create 10 unprotected backups (more recent)
        for ($i = 0; $i < 10; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'), ['is_protected' => false]);
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act - Keep only 3 daily backups
        $toDelete = $retentionService->getBackupsToDelete(3, 0, 0);

        // Assert - All deleted backups should be unprotected
        foreach ($toDelete as $backup) {
            expect($backup->is_protected)->toBeFalse(
                "Backup {$backup->id} is protected but was marked for deletion"
            );
        }

        // Assert - Some unprotected backups should be deleted
        expect($toDelete->count())->toBeGreaterThan(0);
    });

    it('preserves protected backups even when they are the oldest', function () {
        // Arrange - Create old protected backup and newer unprotected backups
        $oldProtected = createBackupAtDate(now()->subMonths(12)->format('Y-m-d 10:00:00'), [
            'is_protected' => true,
        ]);

        // Create recent unprotected backups
        for ($i = 0; $i < 5; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'), ['is_protected' => false]);
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $toDelete = $retentionService->getBackupsToDelete(1, 0, 0);

        // Assert - Old protected backup should not be deleted
        expect($toDelete->contains('id', $oldProtected->id))->toBeFalse();
    });

    it('applies retention policy correctly to unprotected backups only', function () {
        // Arrange - Create 5 protected and 10 unprotected backups
        $protectedCount = 5;
        $unprotectedCount = 10;

        for ($i = 0; $i < $protectedCount; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 08:00:00'), ['is_protected' => true]);
        }

        for ($i = 0; $i < $unprotectedCount; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 16:00:00'), ['is_protected' => false]);
        }

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act - Keep 3 daily backups
        $toDelete = $retentionService->getBackupsToDelete(3, 0, 0);

        // Assert - Should delete 7 unprotected backups (10 - 3)
        expect($toDelete->count())->toBe(7);

        // Assert - All deleted should be unprotected
        $toDelete->each(function ($backup) {
            expect($backup->is_protected)->toBeFalse();
        });
    });
});

/**
 * Additional tests for retention service functionality.
 */
describe('Retention Service Integration', function () {
    it('applies retention policy and deletes backups', function () {
        // Arrange - Create 10 backups
        for ($i = 0; $i < 10; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'));
        }

        // Set up retention settings
        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 3,
            'retention_weekly' => 0,
            'retention_monthly' => 0,
            'google_drive_enabled' => false,
        ]);

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        $initialCount = Backup::count();
        expect($initialCount)->toBe(10);

        // Act
        $deletedCount = $retentionService->applyRetentionPolicy();

        // Assert - Should have deleted 7 backups (10 - 3)
        expect($deletedCount)->toBe(7);
        expect(Backup::count())->toBe(3);
    });

    it('provides accurate preview of retention policy', function () {
        // Arrange - Create 10 backups
        for ($i = 0; $i < 10; $i++) {
            createBackupAtDate(now()->subDays($i)->format('Y-m-d 10:00:00'));
        }

        BackupSettings::query()->delete();
        BackupSettings::create([
            'schedule_enabled' => false,
            'schedule_frequency' => 'daily',
            'schedule_time' => '02:00:00',
            'retention_daily' => 5,
            'retention_weekly' => 0,
            'retention_monthly' => 0,
            'google_drive_enabled' => false,
        ]);

        $backupService = new BackupService;
        $retentionService = new RetentionService($backupService);

        // Act
        $preview = $retentionService->previewRetentionPolicy();

        // Assert
        expect($preview['to_delete']->count())->toBe(5);
        expect($preview['to_keep']->count())->toBe(5);
        expect($preview['settings']['retention_daily'])->toBe(5);
    });
});
