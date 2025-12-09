<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\BackupNotificationService;
use App\Services\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The backup that was created (if any).
     */
    protected ?Backup $backup = null;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BackupService $backupService): void
    {
        Log::info('Scheduled backup job started', [
            'attempt' => $this->attempts(),
        ]);

        $this->backup = $backupService->createBackup('scheduled');

        Log::info('Scheduled backup job completed', [
            'backup_id' => $this->backup->id,
            'filename' => $this->backup->filename,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Scheduled backup job failed after all retries', [
            'attempts' => $this->attempts(),
            'error' => $exception?->getMessage(),
        ]);

        // Send notification about the scheduled backup failure
        $notificationService = app(BackupNotificationService::class);
        $notificationService->notifyScheduledBackupFailure(
            $exception?->getMessage() ?? 'Unknown error',
            $this->backup
        );
    }
}
