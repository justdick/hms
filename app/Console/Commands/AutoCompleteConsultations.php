<?php

namespace App\Console\Commands;

use App\Models\Consultation;
use Illuminate\Console\Command;

class AutoCompleteConsultations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'consultations:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete consultations that have been in progress for more than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoffTime = now()->subHours(24);

        $consultations = Consultation::where('status', 'in_progress')
            ->where('started_at', '<', $cutoffTime)
            ->get();

        if ($consultations->isEmpty()) {
            $this->info('No consultations to auto-complete.');

            return self::SUCCESS;
        }

        $count = $consultations->count();

        foreach ($consultations as $consultation) {
            $consultation->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Also update check-in status to completed
            $consultation->patientCheckin->update([
                'consultation_completed_at' => now(),
                'status' => 'completed',
            ]);

            $this->line("Auto-completed consultation #{$consultation->id} (started: {$consultation->started_at->format('Y-m-d H:i')})");
        }

        $this->info("Successfully auto-completed {$count} consultation(s).");

        return self::SUCCESS;
    }
}
