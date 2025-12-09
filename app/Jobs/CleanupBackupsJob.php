<?php

namespace App\Jobs;

use App\Services\RetentionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupBackupsJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RetentionService $retentionService): void
    {
        Log::info('Backup cleanup job started');

        $deletedCount = $retentionService->applyRetentionPolicy();

        Log::info('Backup cleanup job completed', [
            'deleted_count' => $deletedCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Backup cleanup job failed', [
            'attempts' => $this->attempts(),
            'error' => $exception?->getMessage(),
        ]);
    }
}
