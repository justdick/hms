<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;

class ListBackupsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:list 
                            {--status= : Filter by status (pending, completed, failed)}
                            {--source= : Filter by source (manual_ui, manual_cli, scheduled, pre_restore)}
                            {--limit=20 : Number of backups to display}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all backups with status and storage info';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Backup::query()->orderBy('created_at', 'desc');

        // Apply status filter
        if ($status = $this->option('status')) {
            $validStatuses = ['pending', 'completed', 'failed'];
            if (! in_array($status, $validStatuses)) {
                $this->error("Invalid status '{$status}'. Valid options: ".implode(', ', $validStatuses));

                return Command::FAILURE;
            }
            $query->where('status', $status);
        }

        // Apply source filter
        if ($source = $this->option('source')) {
            $validSources = ['manual_ui', 'manual_cli', 'scheduled', 'pre_restore'];
            if (! in_array($source, $validSources)) {
                $this->error("Invalid source '{$source}'. Valid options: ".implode(', ', $validSources));

                return Command::FAILURE;
            }
            $query->where('source', $source);
        }

        // Apply limit
        $limit = (int) $this->option('limit');
        $backups = $query->limit($limit)->get();

        if ($backups->isEmpty()) {
            $this->info('No backups found.');

            return Command::SUCCESS;
        }

        // Build table rows
        $rows = $backups->map(function ($backup) {
            $storage = [];
            if ($backup->isLocal()) {
                $storage[] = 'Local';
            }
            if ($backup->isOnGoogleDrive()) {
                $storage[] = 'GDrive';
            }

            return [
                $backup->id,
                $backup->filename,
                $this->formatBytes($backup->file_size),
                $this->formatStatus($backup->status),
                $backup->source,
                implode(', ', $storage) ?: 'None',
                $backup->is_protected ? '🔒' : '',
                $backup->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Filename', 'Size', 'Status', 'Source', 'Storage', 'Protected', 'Created At'],
            $rows
        );

        $this->newLine();

        // Display summary
        $totalCount = Backup::count();
        $completedCount = Backup::where('status', 'completed')->count();
        $failedCount = Backup::where('status', 'failed')->count();
        $protectedCount = Backup::where('is_protected', true)->count();

        $this->info("Summary: {$totalCount} total, {$completedCount} completed, {$failedCount} failed, {$protectedCount} protected");

        if ($backups->count() < $totalCount) {
            $this->info("Showing {$backups->count()} of {$totalCount} backups. Use --limit to show more.");
        }

        return Command::SUCCESS;
    }

    /**
     * Format status with color indicator.
     */
    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'completed' => '✓ completed',
            'failed' => '✗ failed',
            'pending' => '⏳ pending',
            default => $status,
        };
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
