<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupLog;
use App\Models\BackupSettings;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RetentionService
{
    /**
     * The backup service instance.
     */
    protected BackupService $backupService;

    /**
     * The user performing the retention cleanup (for audit logging).
     */
    protected ?User $user = null;

    /**
     * Create a new retention service instance.
     */
    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Set the user performing the retention cleanup.
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Apply the retention policy and delete excess backups.
     *
     * @return int The number of backups deleted
     */
    public function applyRetentionPolicy(): int
    {
        $settings = BackupSettings::getInstance();

        // Get backups to delete based on retention policy
        $backupsToDelete = $this->getBackupsToDelete(
            $settings->retention_daily,
            $settings->retention_weekly,
            $settings->retention_monthly
        );

        $deletedCount = 0;

        foreach ($backupsToDelete as $backup) {
            if ($this->deleteBackupWithLogging($backup)) {
                $deletedCount++;
            }
        }

        Log::info('Retention policy applied', [
            'deleted_count' => $deletedCount,
            'retention_daily' => $settings->retention_daily,
            'retention_weekly' => $settings->retention_weekly,
            'retention_monthly' => $settings->retention_monthly,
        ]);

        return $deletedCount;
    }

    /**
     * Get the backups that should be deleted based on retention settings.
     *
     * @param  int  $retainDaily  Number of daily backups to retain
     * @param  int  $retainWeekly  Number of weekly backups to retain
     * @param  int  $retainMonthly  Number of monthly backups to retain
     * @return Collection<int, Backup> Backups to delete
     */
    public function getBackupsToDelete(int $retainDaily, int $retainWeekly, int $retainMonthly): Collection
    {
        // Get all completed, unprotected backups ordered by creation date (newest first)
        $allBackups = Backup::completed()
            ->unprotected()
            ->orderBy('created_at', 'desc')
            ->get();

        // Categorize backups
        $categorized = $this->categorizeBackups($allBackups);

        // Determine which backups to keep
        $backupsToKeep = $this->determineBackupsToKeep(
            $categorized,
            $retainDaily,
            $retainWeekly,
            $retainMonthly
        );

        // Return backups that are not in the keep list
        return $allBackups->filter(function (Backup $backup) use ($backupsToKeep) {
            return ! $backupsToKeep->contains('id', $backup->id);
        });
    }

    /**
     * Categorize backups by daily, weekly, and monthly periods.
     *
     * @param  Collection<int, Backup>  $backups  All backups to categorize
     * @return array{daily: Collection, weekly: Collection, monthly: Collection}
     */
    public function categorizeBackups(Collection $backups): array
    {
        $daily = collect();
        $weekly = collect();
        $monthly = collect();

        // Track which dates/weeks/months we've seen
        $seenDays = [];
        $seenWeeks = [];
        $seenMonths = [];

        foreach ($backups as $backup) {
            $createdAt = $backup->created_at;
            $dayKey = $createdAt->format('Y-m-d');
            $weekKey = $createdAt->format('Y-W'); // Year-Week number
            $monthKey = $createdAt->format('Y-m');

            // Add to daily if we haven't seen this day yet (first backup of the day)
            if (! in_array($dayKey, $seenDays)) {
                $daily->push($backup);
                $seenDays[] = $dayKey;
            }

            // Add to weekly if we haven't seen this week yet (first backup of the week)
            if (! in_array($weekKey, $seenWeeks)) {
                $weekly->push($backup);
                $seenWeeks[] = $weekKey;
            }

            // Add to monthly if we haven't seen this month yet (first backup of the month)
            if (! in_array($monthKey, $seenMonths)) {
                $monthly->push($backup);
                $seenMonths[] = $monthKey;
            }
        }

        return [
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
        ];
    }

    /**
     * Determine which backups to keep based on retention settings.
     *
     * @param  array{daily: Collection, weekly: Collection, monthly: Collection}  $categorized
     * @param  int  $retainDaily  Number of daily backups to retain
     * @param  int  $retainWeekly  Number of weekly backups to retain
     * @param  int  $retainMonthly  Number of monthly backups to retain
     * @return Collection<int, Backup> Backups to keep
     */
    protected function determineBackupsToKeep(
        array $categorized,
        int $retainDaily,
        int $retainWeekly,
        int $retainMonthly
    ): Collection {
        $toKeep = collect();

        // Keep the most recent daily backups
        $dailyToKeep = $categorized['daily']->take($retainDaily);
        $toKeep = $toKeep->merge($dailyToKeep);

        // Keep the most recent weekly backups (that aren't already kept as daily)
        $weeklyToKeep = $categorized['weekly']
            ->filter(fn (Backup $b) => ! $toKeep->contains('id', $b->id))
            ->take($retainWeekly);
        $toKeep = $toKeep->merge($weeklyToKeep);

        // Keep the most recent monthly backups (that aren't already kept)
        $monthlyToKeep = $categorized['monthly']
            ->filter(fn (Backup $b) => ! $toKeep->contains('id', $b->id))
            ->take($retainMonthly);
        $toKeep = $toKeep->merge($monthlyToKeep);

        return $toKeep;
    }

    /**
     * Delete a backup and log the action.
     *
     * @param  Backup  $backup  The backup to delete
     * @return bool True if deletion was successful
     */
    protected function deleteBackupWithLogging(Backup $backup): bool
    {
        $backupId = $backup->id;
        $filename = $backup->filename;

        try {
            // Log the deletion before actually deleting
            BackupLog::create([
                'backup_id' => $backupId,
                'user_id' => $this->user?->id,
                'action' => 'retention_cleanup',
                'details' => "Backup '{$filename}' deleted by retention policy",
            ]);

            // Delete the backup using BackupService
            $result = $this->backupService->deleteBackup($backup);

            if ($result) {
                Log::info('Backup deleted by retention policy', [
                    'backup_id' => $backupId,
                    'filename' => $filename,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to delete backup during retention cleanup', [
                'backup_id' => $backupId,
                'filename' => $filename,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get a preview of what would be deleted without actually deleting.
     *
     * @return array{to_delete: Collection, to_keep: Collection, settings: array}
     */
    public function previewRetentionPolicy(): array
    {
        $settings = BackupSettings::getInstance();

        $allBackups = Backup::completed()
            ->unprotected()
            ->orderBy('created_at', 'desc')
            ->get();

        $categorized = $this->categorizeBackups($allBackups);

        $toKeep = $this->determineBackupsToKeep(
            $categorized,
            $settings->retention_daily,
            $settings->retention_weekly,
            $settings->retention_monthly
        );

        $toDelete = $allBackups->filter(fn (Backup $b) => ! $toKeep->contains('id', $b->id));

        return [
            'to_delete' => $toDelete,
            'to_keep' => $toKeep,
            'settings' => [
                'retention_daily' => $settings->retention_daily,
                'retention_weekly' => $settings->retention_weekly,
                'retention_monthly' => $settings->retention_monthly,
            ],
        ];
    }
}
