<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RestoreService
{
    /**
     * The backup service instance.
     */
    protected ?BackupService $backupService = null;

    /**
     * The Google Drive service instance.
     */
    protected ?GoogleDriveService $googleDriveService = null;

    /**
     * The backup notification service instance.
     */
    protected ?BackupNotificationService $notificationService = null;

    /**
     * The backup audit service instance.
     */
    protected ?BackupAuditService $auditService = null;

    /**
     * The backup storage disk.
     */
    protected string $disk = 'local';

    /**
     * The backup storage path within the disk.
     */
    protected string $storagePath = 'backups';

    /**
     * Restore the database from a backup.
     *
     * @param  Backup  $backup  The backup to restore from
     * @param  User|null  $user  The user who initiated the restore
     * @return bool True if restore was successful
     *
     * @throws RuntimeException If restore fails
     */
    public function restore(Backup $backup, ?User $user = null): bool
    {
        $preRestoreBackup = null;

        try {
            // Log restore attempt
            $this->getAuditService()->logRestoreStarted($backup, $user);

            // Create pre-restore backup
            $preRestoreBackup = $this->createPreRestoreBackup($user);

            $this->getAuditService()->logPreRestoreBackupCreated($backup, $preRestoreBackup, $user);

            // Get the backup file path (download from Google Drive if needed)
            $filePath = $this->getBackupFilePath($backup);

            // Execute the restore
            $this->executeRestore($filePath);

            // Log successful restore
            $this->getAuditService()->logRestoreCompleted($backup, $user);

            Log::info('Database restore completed successfully', [
                'backup_id' => $backup->id,
                'backup_filename' => $backup->filename,
                'pre_restore_backup_id' => $preRestoreBackup->id,
                'user_id' => $user?->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Database restore failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            // Log the failure
            $this->getAuditService()->logRestoreFailed($backup, $e->getMessage(), $user);

            // Send failure notification
            $this->getNotificationService()->notifyRestoreFailure($backup, $e->getMessage());

            // Attempt to restore from pre-restore backup if it exists
            if ($preRestoreBackup !== null) {
                $this->attemptRecovery($preRestoreBackup, $backup, $user, $e);
            }

            throw new RuntimeException('Database restore failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a pre-restore backup of the current database state.
     *
     * @param  User|null  $user  The user who initiated the restore
     * @return Backup The created pre-restore backup
     *
     * @throws RuntimeException If pre-restore backup creation fails
     */
    public function createPreRestoreBackup(?User $user = null): Backup
    {
        try {
            $backupService = $this->getBackupService();

            // Create backup with 'pre_restore' source identifier
            $backup = $backupService->createBackup('pre_restore', $user);

            Log::info('Pre-restore backup created', [
                'backup_id' => $backup->id,
                'filename' => $backup->filename,
                'user_id' => $user?->id,
            ]);

            return $backup;

        } catch (\Exception $e) {
            Log::error('Pre-restore backup creation failed', [
                'error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);

            throw new RuntimeException('Failed to create pre-restore backup: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get the backup file path, downloading from Google Drive if necessary.
     *
     * @param  Backup  $backup  The backup to get the file path for
     * @return string The local file path
     *
     * @throws RuntimeException If the file cannot be obtained
     */
    protected function getBackupFilePath(Backup $backup): string
    {
        // Check if file exists locally
        if ($backup->isLocal() && Storage::disk($this->disk)->exists($backup->file_path)) {
            return Storage::disk($this->disk)->path($backup->file_path);
        }

        // Try to download from Google Drive
        if ($backup->isOnGoogleDrive()) {
            return $this->downloadFromGoogleDrive($backup);
        }

        throw new RuntimeException('Backup file is not available locally or on Google Drive.');
    }

    /**
     * Download a backup file from Google Drive.
     *
     * @param  Backup  $backup  The backup to download
     * @return string The local file path
     *
     * @throws RuntimeException If download fails
     */
    protected function downloadFromGoogleDrive(Backup $backup): string
    {
        $googleDriveService = $this->getGoogleDriveService();

        if (! $googleDriveService->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured.');
        }

        try {
            // Download file content
            $content = $googleDriveService->download($backup->google_drive_file_id);

            // Save to local storage
            $localPath = $this->storagePath.'/'.$backup->filename;
            Storage::disk($this->disk)->put($localPath, $content);

            // Update backup record with local path
            $backup->file_path = $localPath;
            $backup->save();

            Log::info('Backup downloaded from Google Drive', [
                'backup_id' => $backup->id,
                'filename' => $backup->filename,
                'local_path' => $localPath,
            ]);

            return Storage::disk($this->disk)->path($localPath);

        } catch (\Exception $e) {
            Log::error('Failed to download backup from Google Drive', [
                'backup_id' => $backup->id,
                'google_drive_file_id' => $backup->google_drive_file_id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to download backup from Google Drive: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute the database restore from a backup file.
     *
     * @param  string  $filePath  The path to the backup file
     * @return bool True if restore was successful
     *
     * @throws RuntimeException If restore fails
     */
    protected function executeRestore(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException('Backup file not found: '.$filePath);
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            return $this->executeSqliteRestore($filePath);
        }

        return $this->executeMysqlRestore($filePath);
    }

    /**
     * Execute MySQL restore from a backup file.
     *
     * @param  string  $filePath  The path to the backup file
     * @return bool True if restore was successful
     *
     * @throws RuntimeException If restore fails
     */
    protected function executeMysqlRestore(string $filePath): bool
    {
        $config = config('database.connections.mysql');

        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        // Get mysql path from config (same location as mysqldump)
        $mysqlDumpPath = config('database.connections.mysql.dump.dump_binary_path')
            ?? env('MYSQL_DUMP_PATH', 'mysqldump');
        // Derive mysql path from mysqldump path
        $mysqlPath = str_replace('mysqldump', 'mysql', $mysqlDumpPath);

        // Stream decompress to temp file to avoid memory issues
        $tempSqlFile = sys_get_temp_dir().'/'.uniqid('hms_restore_').'.sql';
        $this->streamGzipDecompress($filePath, $tempSqlFile);

        $tempErrorFile = sys_get_temp_dir().'/'.uniqid('mysql_err_').'.txt';

        try {
            // Build mysql command
            $commandParts = [
                escapeshellarg($mysqlPath),
                '--host='.escapeshellarg($host),
                '--port='.escapeshellarg($port),
                '--user='.escapeshellarg($username),
            ];

            if (! empty($password)) {
                $commandParts[] = '--password='.escapeshellarg($password);
            }

            $commandParts[] = escapeshellarg($database);

            // Redirect input from temp file, errors to separate file
            $command = implode(' ', $commandParts).' < '.escapeshellarg($tempSqlFile).' 2> '.escapeshellarg($tempErrorFile);

            // Execute using exec()
            $returnCode = 0;
            exec($command, $output, $returnCode);

            // Check for errors
            $errorOutput = file_exists($tempErrorFile) ? trim(file_get_contents($tempErrorFile)) : '';

            if ($returnCode !== 0) {
                throw new RuntimeException('mysql restore failed: '.$errorOutput);
            }

            return true;

        } finally {
            // Clean up temp files
            @unlink($tempSqlFile);
            @unlink($tempErrorFile);
        }
    }

    /**
     * Stream decompress a gzip file to avoid memory issues.
     *
     * @param  string  $sourcePath  The source gzip file path
     * @param  string  $destPath  The destination file path
     *
     * @throws RuntimeException If decompression fails
     */
    protected function streamGzipDecompress(string $sourcePath, string $destPath): void
    {
        $gzHandle = gzopen($sourcePath, 'rb');
        if ($gzHandle === false) {
            throw new RuntimeException('Failed to open gzip file for decompression.');
        }

        $destHandle = fopen($destPath, 'wb');
        if ($destHandle === false) {
            gzclose($gzHandle);
            throw new RuntimeException('Failed to create destination file for decompression.');
        }

        // Stream in 1MB chunks
        while (! gzeof($gzHandle)) {
            $chunk = gzread($gzHandle, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            fwrite($destHandle, $chunk);
        }

        gzclose($gzHandle);
        fclose($destHandle);
    }

    /**
     * Execute SQLite restore from a backup file.
     *
     * @param  string  $filePath  The path to the backup file
     * @return bool True if restore was successful
     *
     * @throws RuntimeException If restore fails
     */
    protected function executeSqliteRestore(string $filePath): bool
    {
        $config = config('database.connections.sqlite');
        $databasePath = $config['database'];

        // Decompress the gzip file
        $sqlContent = $this->decompressGzip($filePath);

        // Handle in-memory database (used in tests)
        if ($databasePath === ':memory:') {
            $pdo = DB::connection('sqlite')->getPdo();
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Get all existing tables and drop them
            $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
            }

            // Execute the SQL content - this will throw PDOException on invalid SQL
            try {
                $pdo->exec($sqlContent);
            } catch (\PDOException $e) {
                throw new RuntimeException('Failed to execute SQL restore: '.$e->getMessage(), 0, $e);
            }

            return true;
        }

        // For file-based SQLite database
        $pdo = new \PDO('sqlite:'.$databasePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Get all existing tables and drop them
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }

        // Execute the SQL content
        $pdo->exec($sqlContent);

        return true;
    }

    /**
     * Decompress a gzip file and return its content.
     *
     * @param  string  $filePath  The path to the gzip file
     * @return string The decompressed content
     *
     * @throws RuntimeException If decompression fails
     */
    protected function decompressGzip(string $filePath): string
    {
        $compressedContent = file_get_contents($filePath);

        if ($compressedContent === false) {
            throw new RuntimeException('Failed to read backup file.');
        }

        $content = gzdecode($compressedContent);

        if ($content === false) {
            throw new RuntimeException('Failed to decompress backup file.');
        }

        return $content;
    }

    /**
     * Attempt to recover from a failed restore by restoring the pre-restore backup.
     *
     * @param  Backup  $preRestoreBackup  The pre-restore backup
     * @param  Backup  $originalBackup  The original backup that failed to restore
     * @param  User|null  $user  The user who initiated the restore
     * @param  \Exception  $originalException  The original exception
     */
    protected function attemptRecovery(
        Backup $preRestoreBackup,
        Backup $originalBackup,
        ?User $user,
        \Exception $originalException
    ): void {
        try {
            $this->getAuditService()->logRecoveryStarted($originalBackup, $user);

            // Get the pre-restore backup file path
            $filePath = Storage::disk($this->disk)->path($preRestoreBackup->file_path);

            // Execute the restore
            $this->executeRestore($filePath);

            $this->getAuditService()->logRecoveryCompleted($originalBackup, $preRestoreBackup, $user);

            Log::info('Recovery from failed restore successful', [
                'original_backup_id' => $originalBackup->id,
                'pre_restore_backup_id' => $preRestoreBackup->id,
                'user_id' => $user?->id,
            ]);

        } catch (\Exception $e) {
            $this->getAuditService()->logRecoveryFailed($originalBackup, $e->getMessage(), $user);

            Log::critical('Recovery from failed restore also failed', [
                'original_backup_id' => $originalBackup->id,
                'pre_restore_backup_id' => $preRestoreBackup->id,
                'original_error' => $originalException->getMessage(),
                'recovery_error' => $e->getMessage(),
                'user_id' => $user?->id,
            ]);
        }
    }

    /**
     * Get the backup service instance.
     *
     * @return BackupService The backup service
     */
    protected function getBackupService(): BackupService
    {
        if ($this->backupService === null) {
            $this->backupService = app(BackupService::class);
        }

        return $this->backupService;
    }

    /**
     * Set the backup service instance (for testing).
     *
     * @param  BackupService  $service  The backup service
     */
    public function setBackupService(BackupService $service): void
    {
        $this->backupService = $service;
    }

    /**
     * Get the Google Drive service instance.
     *
     * @return GoogleDriveService The Google Drive service
     */
    protected function getGoogleDriveService(): GoogleDriveService
    {
        if ($this->googleDriveService === null) {
            $this->googleDriveService = app(GoogleDriveService::class);
        }

        return $this->googleDriveService;
    }

    /**
     * Set the Google Drive service instance (for testing).
     *
     * @param  GoogleDriveService  $service  The Google Drive service
     */
    public function setGoogleDriveService(GoogleDriveService $service): void
    {
        $this->googleDriveService = $service;
    }

    /**
     * Get the backup notification service instance.
     *
     * @return BackupNotificationService The notification service
     */
    protected function getNotificationService(): BackupNotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = app(BackupNotificationService::class);
        }

        return $this->notificationService;
    }

    /**
     * Set the backup notification service instance (for testing).
     *
     * @param  BackupNotificationService  $service  The notification service
     */
    public function setNotificationService(BackupNotificationService $service): void
    {
        $this->notificationService = $service;
    }

    /**
     * Get the backup audit service instance.
     *
     * @return BackupAuditService The audit service
     */
    protected function getAuditService(): BackupAuditService
    {
        if ($this->auditService === null) {
            $this->auditService = app(BackupAuditService::class);
        }

        return $this->auditService;
    }

    /**
     * Set the backup audit service instance (for testing).
     *
     * @param  BackupAuditService  $service  The audit service
     */
    public function setAuditService(BackupAuditService $service): void
    {
        $this->auditService = $service;
    }
}
