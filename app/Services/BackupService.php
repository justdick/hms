<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupService
{
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
     * Create a new database backup.
     *
     * @param  string  $source  The source of the backup (manual_ui, manual_cli, scheduled, pre_restore)
     * @param  User|null  $user  The user who initiated the backup
     * @return Backup The created backup record
     *
     * @throws RuntimeException If backup creation fails
     */
    public function createBackup(string $source, ?User $user = null): Backup
    {
        // Generate filename upfront
        $filename = $this->generateFilename();
        $filePath = $this->storagePath.'/'.$filename;

        try {
            // Execute database dump
            $this->executeDatabaseDump($filename);

            // Get file size
            $fileSize = Storage::disk($this->disk)->size($filePath);

            // Create backup record with all data
            $backup = $this->recordBackupMetadata([
                'filename' => $filename,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'source' => $source,
                'created_by' => $user?->id,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Attempt Google Drive upload (graceful failure)
            $googleDriveFileId = $this->uploadToGoogleDrive($backup);
            if ($googleDriveFileId) {
                $backup->google_drive_file_id = $googleDriveFileId;
                $backup->save();
            }

            // Log audit entry for successful backup creation
            $this->getAuditService()->logCreated($backup, $user);

            Log::info('Backup created successfully', [
                'backup_id' => $backup->id,
                'filename' => $filename,
                'file_size' => $fileSize,
                'source' => $source,
                'google_drive' => $googleDriveFileId ? 'uploaded' : 'skipped',
            ]);

            return $backup;

        } catch (\Exception $e) {
            // Clean up any partial file
            if (Storage::disk($this->disk)->exists($filePath)) {
                Storage::disk($this->disk)->delete($filePath);
            }

            // Create failed backup record for audit trail
            $backup = $this->recordBackupMetadata([
                'filename' => $filename,
                'file_path' => $filePath,
                'file_size' => 0,
                'source' => $source,
                'created_by' => $user?->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Backup creation failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
                'source' => $source,
            ]);

            // Send failure notification
            $this->getNotificationService()->notifyBackupFailure($backup, $e->getMessage());

            throw new RuntimeException('Backup creation failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Upload a backup to Google Drive.
     *
     * @param  Backup  $backup  The backup to upload
     * @return string|null The Google Drive file ID, or null on failure
     */
    protected function uploadToGoogleDrive(Backup $backup): ?string
    {
        $googleDriveService = $this->getGoogleDriveService();

        if (! $googleDriveService->isConfigured()) {
            Log::info('Google Drive upload skipped - not configured');

            return null;
        }

        $fullPath = Storage::disk($this->disk)->path($backup->file_path);

        return $googleDriveService->upload($fullPath, $backup->filename);
    }

    /**
     * Delete a backup from local storage, Google Drive, and database.
     *
     * @param  Backup  $backup  The backup to delete
     * @param  User|null  $user  The user who initiated the deletion
     * @return bool True if deletion was successful
     */
    public function deleteBackup(Backup $backup, ?User $user = null): bool
    {
        try {
            // Log audit entry before deletion (while backup still exists)
            $this->getAuditService()->logDeleted($backup, $user);

            // Delete from Google Drive if it exists there
            if ($backup->isOnGoogleDrive()) {
                $this->deleteFromGoogleDrive($backup);
            }

            // Delete local file if it exists
            if ($backup->isLocal() && Storage::disk($this->disk)->exists($backup->file_path)) {
                Storage::disk($this->disk)->delete($backup->file_path);
            }

            // Delete database record
            $backup->delete();

            Log::info('Backup deleted successfully', [
                'backup_id' => $backup->id,
                'filename' => $backup->filename,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Backup deletion failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete a backup from Google Drive.
     *
     * @param  Backup  $backup  The backup to delete
     * @return bool True if deletion was successful
     */
    protected function deleteFromGoogleDrive(Backup $backup): bool
    {
        if (! $backup->isOnGoogleDrive()) {
            return true;
        }

        $googleDriveService = $this->getGoogleDriveService();

        if (! $googleDriveService->isConfigured()) {
            Log::warning('Google Drive delete skipped - not configured', [
                'backup_id' => $backup->id,
            ]);

            return false;
        }

        return $googleDriveService->delete($backup->google_drive_file_id);
    }

    /**
     * Download a backup file.
     *
     * @param  Backup  $backup  The backup to download
     * @return StreamedResponse The file download response
     *
     * @throws RuntimeException If the backup file doesn't exist
     */
    public function downloadBackup(Backup $backup): StreamedResponse
    {
        if (! $backup->isLocal()) {
            throw new RuntimeException('Backup file is not available locally.');
        }

        $filePath = $backup->file_path;

        if (! Storage::disk($this->disk)->exists($filePath)) {
            throw new RuntimeException('Backup file not found on disk.');
        }

        return Storage::disk($this->disk)->download($filePath, $backup->filename);
    }

    /**
     * Get the full path to a backup file.
     *
     * @param  Backup  $backup  The backup
     * @return string The full file path
     */
    public function getBackupFilePath(Backup $backup): string
    {
        return Storage::disk($this->disk)->path($backup->file_path);
    }

    /**
     * Check if a backup file exists locally.
     *
     * @param  Backup  $backup  The backup to check
     * @return bool True if the file exists
     */
    public function backupFileExists(Backup $backup): bool
    {
        if (! $backup->isLocal()) {
            return false;
        }

        return Storage::disk($this->disk)->exists($backup->file_path);
    }

    /**
     * Execute the database dump command.
     *
     * @param  string  $filename  The filename for the backup
     * @return string The path to the created backup file
     *
     * @throws RuntimeException If the dump fails
     */
    protected function executeDatabaseDump(string $filename): string
    {
        // Ensure backup directory exists
        $backupDir = Storage::disk($this->disk)->path($this->storagePath);
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $outputPath = $backupDir.'/'.$filename;
        $connection = config('database.default');

        if ($connection === 'sqlite') {
            return $this->executeSqliteDump($outputPath);
        }

        return $this->executeMysqlDump($outputPath);
    }

    /**
     * Execute MySQL dump command.
     *
     * @param  string  $outputPath  The output file path
     * @return string The path to the created backup file
     *
     * @throws RuntimeException If the dump fails
     */
    protected function executeMysqlDump(string $outputPath): string
    {
        $config = config('database.connections.mysql');

        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        // Get mysqldump path from config or use default
        $mysqldumpPath = config('database.connections.mysql.dump.dump_binary_path')
            ?? env('MYSQL_DUMP_PATH', 'mysqldump');

        // Create temp file for SQL output
        $tempSqlFile = sys_get_temp_dir().'/'.uniqid('mysqldump_').'.sql';
        $tempErrorFile = sys_get_temp_dir().'/'.uniqid('mysqldump_err_').'.txt';

        // Build command string for shell execution
        $commandParts = [
            escapeshellarg($mysqldumpPath),
            '--host='.escapeshellarg($host),
            '--port='.escapeshellarg($port),
            '--user='.escapeshellarg($username),
            '--single-transaction',
            '--routines',
            '--triggers',
        ];

        if (! empty($password)) {
            $commandParts[] = '--password='.escapeshellarg($password);
        }

        $commandParts[] = escapeshellarg($database);

        // Redirect output to temp file, errors to separate file
        $command = implode(' ', $commandParts).' > '.escapeshellarg($tempSqlFile).' 2> '.escapeshellarg($tempErrorFile);

        // Execute using exec()
        $returnCode = 0;
        exec($command, $output, $returnCode);

        // Check for errors
        $errorOutput = file_exists($tempErrorFile) ? trim(file_get_contents($tempErrorFile)) : '';
        @unlink($tempErrorFile);

        if ($returnCode !== 0) {
            @unlink($tempSqlFile);
            throw new RuntimeException('mysqldump failed: '.$errorOutput);
        }

        if (! file_exists($tempSqlFile) || filesize($tempSqlFile) === 0) {
            @unlink($tempSqlFile);
            throw new RuntimeException('mysqldump produced empty output. '.$errorOutput);
        }

        // Stream compress to avoid memory issues with large databases
        $this->streamGzipCompress($tempSqlFile, $outputPath);

        @unlink($tempSqlFile);

        return $outputPath;
    }

    /**
     * Stream compress a file using gzip to avoid memory issues.
     *
     * @param  string  $sourcePath  The source file path
     * @param  string  $destPath  The destination gzip file path
     *
     * @throws RuntimeException If compression fails
     */
    protected function streamGzipCompress(string $sourcePath, string $destPath): void
    {
        $sourceHandle = fopen($sourcePath, 'rb');
        if ($sourceHandle === false) {
            throw new RuntimeException('Failed to open source file for compression.');
        }

        $gzHandle = gzopen($destPath, 'wb9');
        if ($gzHandle === false) {
            fclose($sourceHandle);
            throw new RuntimeException('Failed to create gzip file.');
        }

        // Stream in 1MB chunks to keep memory usage low
        while (! feof($sourceHandle)) {
            $chunk = fread($sourceHandle, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($gzHandle, $chunk);
        }

        fclose($sourceHandle);
        gzclose($gzHandle);
    }

    /**
     * Execute SQLite dump (for testing/development).
     *
     * @param  string  $outputPath  The output file path
     * @return string The path to the created backup file
     *
     * @throws RuntimeException If the dump fails
     */
    protected function executeSqliteDump(string $outputPath): string
    {
        $config = config('database.connections.sqlite');
        $databasePath = $config['database'];

        // Handle in-memory database (used in tests)
        if ($databasePath === ':memory:') {
            // Get the existing PDO connection from Laravel
            $pdo = \DB::connection('sqlite')->getPdo();
            $sqlContent = $this->generateSqliteDump($pdo);
        } else {
            if (! file_exists($databasePath)) {
                throw new RuntimeException('SQLite database file not found.');
            }

            // Read SQLite database and create SQL dump
            $pdo = new \PDO('sqlite:'.$databasePath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $sqlContent = $this->generateSqliteDump($pdo);
        }

        // Compress with gzip
        $compressedContent = gzencode($sqlContent, 9);

        if ($compressedContent === false) {
            throw new RuntimeException('Failed to compress backup file.');
        }

        // Write compressed content to file
        if (file_put_contents($outputPath, $compressedContent) === false) {
            throw new RuntimeException('Failed to write backup file.');
        }

        return $outputPath;
    }

    /**
     * Generate SQL dump from SQLite database.
     *
     * @param  \PDO  $pdo  The PDO connection
     * @return string The SQL dump content
     */
    protected function generateSqliteDump(\PDO $pdo): string
    {
        $output = "-- HMS Database Backup\n";
        $output .= '-- Generated: '.now()->toDateTimeString()."\n";
        $output .= "-- SQLite Database Dump\n\n";

        // Get all tables
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            // Get table schema
            $schema = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
            $output .= "{$schema};\n\n";

            // Get table data
            $rows = $pdo->query("SELECT * FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote($value);
                }, array_values($row));

                $output .= "INSERT INTO {$table} (".implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n";
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * Generate a unique filename for the backup.
     *
     * @return string The generated filename
     */
    protected function generateFilename(): string
    {
        return 'hms_backup_'.now()->format('Ymd_His').'.sql.gz';
    }

    /**
     * Record backup metadata in the database.
     *
     * @param  array  $data  The backup data
     * @return Backup The created backup record
     */
    protected function recordBackupMetadata(array $data): Backup
    {
        return Backup::create([
            'filename' => $data['filename'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'google_drive_file_id' => $data['google_drive_file_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'source' => $data['source'] ?? 'manual_ui',
            'is_protected' => $data['is_protected'] ?? false,
            'created_by' => $data['created_by'] ?? null,
            'completed_at' => $data['completed_at'] ?? null,
            'error_message' => $data['error_message'] ?? null,
        ]);
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
