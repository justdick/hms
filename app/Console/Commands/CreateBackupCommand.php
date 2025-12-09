<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class CreateBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:create {--source=cli : The source of the backup (cli, scheduled)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a database backup';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $source = $this->option('source');

        // Validate source option
        $validSources = ['cli', 'scheduled', 'manual_cli'];
        if (! in_array($source, $validSources)) {
            $this->error("Invalid source '{$source}'. Valid options: ".implode(', ', $validSources));

            return Command::FAILURE;
        }

        // Normalize source to match expected values
        if ($source === 'cli') {
            $source = 'manual_cli';
        }

        $this->info('Creating database backup...');
        $this->newLine();

        try {
            $backup = $backupService->createBackup($source);

            $this->info('✓ Backup created successfully!');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['ID', $backup->id],
                    ['Filename', $backup->filename],
                    ['File Size', $this->formatBytes($backup->file_size)],
                    ['Status', $backup->status],
                    ['Source', $backup->source],
                    ['Local Storage', $backup->isLocal() ? 'Yes' : 'No'],
                    ['Google Drive', $backup->isOnGoogleDrive() ? 'Yes' : 'No'],
                    ['Created At', $backup->created_at->format('Y-m-d H:i:s')],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Backup creation failed!');
            $this->error($e->getMessage());

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
