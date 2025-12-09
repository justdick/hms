<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\BackupLog;
use App\Models\User;

class BackupAuditService
{
    /**
     * Log a backup creation.
     *
     * @param  Backup  $backup  The created backup
     * @param  User|null  $user  The user who created the backup
     */
    public function logCreated(Backup $backup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'created',
            "Backup created: {$backup->filename} (source: {$backup->source}, size: {$this->formatFileSize($backup->file_size)})"
        );
    }

    /**
     * Log a backup deletion.
     *
     * @param  Backup  $backup  The deleted backup
     * @param  User|null  $user  The user who deleted the backup
     */
    public function logDeleted(Backup $backup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'deleted',
            "Backup deleted: {$backup->filename}"
        );
    }

    /**
     * Log a restore operation start.
     *
     * @param  Backup  $backup  The backup being restored
     * @param  User|null  $user  The user who initiated the restore
     */
    public function logRestoreStarted(Backup $backup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'restore_started',
            "Restore operation initiated from backup: {$backup->filename}"
        );
    }

    /**
     * Log a pre-restore backup creation.
     *
     * @param  Backup  $originalBackup  The backup being restored from
     * @param  Backup  $preRestoreBackup  The pre-restore backup created
     * @param  User|null  $user  The user who initiated the restore
     */
    public function logPreRestoreBackupCreated(Backup $originalBackup, Backup $preRestoreBackup, ?User $user = null): BackupLog
    {
        return $this->log(
            $originalBackup,
            $user,
            'pre_restore_backup_created',
            "Pre-restore backup created: {$preRestoreBackup->filename}"
        );
    }

    /**
     * Log a successful restore completion.
     *
     * @param  Backup  $backup  The backup that was restored
     * @param  User|null  $user  The user who initiated the restore
     */
    public function logRestoreCompleted(Backup $backup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'restore_completed',
            "Database restored successfully from backup: {$backup->filename}"
        );
    }

    /**
     * Log a failed restore operation.
     *
     * @param  Backup  $backup  The backup that failed to restore
     * @param  string  $error  The error message
     * @param  User|null  $user  The user who initiated the restore
     */
    public function logRestoreFailed(Backup $backup, string $error, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'restore_failed',
            "Restore failed: {$error}"
        );
    }

    /**
     * Log a recovery attempt start.
     *
     * @param  Backup  $backup  The original backup
     * @param  User|null  $user  The user
     */
    public function logRecoveryStarted(Backup $backup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'recovery_started',
            'Attempting to restore pre-restore backup after failure'
        );
    }

    /**
     * Log a successful recovery.
     *
     * @param  Backup  $backup  The original backup
     * @param  Backup  $preRestoreBackup  The pre-restore backup used for recovery
     * @param  User|null  $user  The user
     */
    public function logRecoveryCompleted(Backup $backup, Backup $preRestoreBackup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'recovery_completed',
            "Successfully restored pre-restore backup: {$preRestoreBackup->filename}"
        );
    }

    /**
     * Log a failed recovery.
     *
     * @param  Backup  $backup  The original backup
     * @param  string  $error  The error message
     * @param  User|null  $user  The user
     */
    public function logRecoveryFailed(Backup $backup, string $error, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'recovery_failed',
            "Recovery failed: {$error}"
        );
    }

    /**
     * Log a backup download.
     *
     * @param  Backup  $backup  The downloaded backup
     * @param  User|null  $user  The user who downloaded the backup
     */
    public function logDownloaded(Backup $backup, ?User $user = null): BackupLog
    {
        return $this->log(
            $backup,
            $user,
            'downloaded',
            "Backup downloaded: {$backup->filename}"
        );
    }

    /**
     * Log a settings change.
     *
     * @param  array  $changes  The settings that were changed
     * @param  User|null  $user  The user who changed the settings
     */
    public function logSettingsChanged(array $changes, ?User $user = null): BackupLog
    {
        $changesDescription = implode(', ', array_keys($changes));

        return BackupLog::create([
            'backup_id' => null,
            'user_id' => $user?->id,
            'action' => 'settings_changed',
            'details' => "Backup settings changed: {$changesDescription}",
        ]);
    }

    /**
     * Log a retention cleanup operation.
     *
     * @param  int  $deletedCount  The number of backups deleted
     * @param  User|null  $user  The user who triggered the cleanup (null for scheduled)
     */
    public function logRetentionCleanup(int $deletedCount, ?User $user = null): BackupLog
    {
        return BackupLog::create([
            'backup_id' => null,
            'user_id' => $user?->id,
            'action' => 'retention_cleanup',
            'details' => "Retention cleanup completed: {$deletedCount} backup(s) deleted",
        ]);
    }

    /**
     * Create a backup log entry.
     *
     * @param  Backup  $backup  The backup
     * @param  User|null  $user  The user
     * @param  string  $action  The action
     * @param  string  $details  The details
     */
    protected function log(Backup $backup, ?User $user, string $action, string $details): BackupLog
    {
        return BackupLog::create([
            'backup_id' => $backup->id,
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details,
        ]);
    }

    /**
     * Format file size for human readability.
     *
     * @param  int|null  $bytes  The file size in bytes
     * @return string The formatted file size
     */
    protected function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
