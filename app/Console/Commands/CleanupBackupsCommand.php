<?php

namespace App\Console\Commands;

use App\Models\BackupSettings;
use App\Services\RetentionService;
use Illuminate\Console\Command;

class CleanupBackupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:cleanup 
                            {--preview : Preview what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply retention policy and cleanup old backups';

    /**
     * Execute the console command.
     */
    public function handle(RetentionService $retentionService): int
    {
        $settings = BackupSettings::getInstance();

        $this->info('Retention Policy Settings:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Daily Backups to Keep', $settings->retention_daily],
                ['Weekly Backups to Keep', $settings->retention_weekly],
                ['Monthly Backups to Keep', $settings->retention_monthly],
            ]
        );

        $this->newLine();

        if ($this->option('preview')) {
            return $this->previewCleanup($retentionService);
        }

        return $this->executeCleanup($retentionService);
    }

    /**
     * Preview what would be deleted without actually deleting.
     */
    protected function previewCleanup(RetentionService $retentionService): int
    {
        $this->info('Preview Mode - No backups will be deleted');
        $this->newLine();

        $preview = $retentionService->previewRetentionPolicy();

        if ($preview['to_delete']->isEmpty()) {
            $this->info('No backups would be deleted based on current retention policy.');

            return Command::SUCCESS;
        }

        $this->warn('The following backups would be deleted:');
        $this->newLine();

        $rows = $preview['to_delete']->map(function ($backup) {
            return [
                $backup->id,
                $backup->filename,
                $this->formatBytes($backup->file_size),
                $backup->created_at->format('Y-m-d H:i:s'),
                $backup->source,
            ];
        })->toArray();

        $this->table(
            ['ID', 'Filename', 'Size', 'Created At', 'Source'],
            $rows
        );

        $this->newLine();
        $this->info("Total: {$preview['to_delete']->count()} backup(s) would be deleted.");
        $this->info("Backups to keep: {$preview['to_keep']->count()}");

        return Command::SUCCESS;
    }

    /**
     * Execute the cleanup operation.
     */
    protected function executeCleanup(RetentionService $retentionService): int
    {
        $this->info('Applying retention policy...');
        $this->newLine();

        try {
            $deletedCount = $retentionService->applyRetentionPolicy();

            if ($deletedCount === 0) {
                $this->info('No backups needed to be deleted.');
            } else {
                $this->info('✓ Cleanup completed successfully!');
                $this->info("  Deleted {$deletedCount} backup(s).");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('✗ Cleanup failed!');
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
