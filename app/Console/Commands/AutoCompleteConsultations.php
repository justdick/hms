<?php

namespace App\Console\Commands;

use App\Models\Consultation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            ->with('patientCheckin')
            ->get();

        if ($consultations->isEmpty()) {
            $this->info('No consultations to auto-complete.');

            return self::SUCCESS;
        }

        $count = $consultations->count();

        foreach ($consultations as $consultation) {
            try {
                DB::transaction(function () use ($consultation) {
                    $consultation->markCompleted();
                });

                $this->line("Auto-completed consultation #{$consultation->id} (started: {$consultation->started_at->format('Y-m-d H:i')})");
            } catch (\Throwable $e) {
                $this->error("Failed to auto-complete consultation #{$consultation->id}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully auto-completed {$count} consultation(s).");

        return self::SUCCESS;
    }
}
