<?php

namespace App\Console\Commands;

use App\Models\PatientCheckin;
use Illuminate\Console\Command;

class CancelOldIncompleteCheckins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkins:cancel-old {--hours=24 : Hours after which incomplete check-ins are cancelled}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically cancel incomplete check-ins older than specified hours (default: 24 hours)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $threshold = now()->subHours($hours);

        $this->info("Cancelling incomplete check-ins older than {$hours} hours (before {$threshold->format('Y-m-d H:i:s')})...");

        // Find incomplete check-ins older than threshold
        $oldCheckins = PatientCheckin::whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
            ->where('checked_in_at', '<', $threshold)
            ->get();

        if ($oldCheckins->isEmpty()) {
            $this->info('No incomplete check-ins found to cancel.');

            return Command::SUCCESS;
        }

        $cancelledCount = 0;
        $failedCount = 0;

        foreach ($oldCheckins as $checkin) {
            try {
                $checkin->cancel("Auto-cancelled: No activity for {$hours} hours");
                $cancelledCount++;

                $this->line("✓ Cancelled check-in #{$checkin->id} for patient {$checkin->patient->full_name}");
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("✗ Failed to cancel check-in #{$checkin->id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  - Total found: {$oldCheckins->count()}");
        $this->info("  - Cancelled: {$cancelledCount}");

        if ($failedCount > 0) {
            $this->warn("  - Failed: {$failedCount}");
        }

        return Command::SUCCESS;
    }
}
