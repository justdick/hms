<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\RestoreService;
use Illuminate\Console\Command;

class RestoreBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore 
                            {backup : The ID of the backup to restore}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore database from a backup';

    /**
     * Execute the console command.
     */
    public function handle(RestoreService $restoreService): int
    {
        $backupId = $this->argument('backup');

        // Find the backup
        $backup = Backup::find($backupId);

        if (! $backup) {
            $this->error("Backup with ID '{$backupId}' not found.");

            return Command::FAILURE;
        }

        // Check if backup is completed
        if ($backup->status !== 'completed') {
            $this->error("Cannot restore from backup with status '{$backup->status}'. Only completed backups can be restored.");

            return Command::FAILURE;
        }

        // Display backup information
        $this->info('Backup Information:');
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $backup->id],
                ['Filename', $backup->filename],
                ['File Size', $this->formatBytes($backup->file_size)],
                ['Created At', $backup->created_at->format('Y-m-d H:i:s')],
                ['Source', $backup->source],
                ['Local Storage', $backup->isLocal() ? 'Yes' : 'No'],
                ['Google Drive', $backup->isOnGoogleDrive() ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();

        // Require confirmation unless --force is used
        if (! $this->option('force')) {
            $this->warn('⚠️  WARNING: This will replace the current database with the backup data!');
            $this->warn('   A pre-restore backup will be created automatically.');
            $this->newLine();

            if (! $this->confirm('Are you sure you want to restore from this backup?', false)) {
                $this->info('Restore cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->newLine();
        $this->info('Starting database restore...');
        $this->info('Creating pre-restore backup...');

        try {
            $restoreService->restore($backup);

            $this->newLine();
            $this->info('✓ Database restored successfully!');
            $this->info('  Restored from: '.$backup->filename);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('✗ Database restore failed!');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Note: A pre-restore backup was created. If the restore failed after the backup,');
            $this->warn('      the system attempted to recover to the pre-restore state.');

            return Command::FAILURE;
        }
    }

    /**
     * Format bytes to human-readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2).' '.$units[$pow];
    }
}
