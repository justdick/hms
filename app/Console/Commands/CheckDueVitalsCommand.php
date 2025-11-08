<?php

namespace App\Console\Commands;

use App\Models\VitalsSchedule;
use App\Services\VitalsAlertService;
use Illuminate\Console\Command;

class CheckDueVitalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vitals:check-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for due and overdue vitals schedules';

    /**
     * Execute the console command.
     */
    public function handle(VitalsAlertService $alertService): int
    {
        $now = now();
        $gracePeriodEnd = $now->copy()->subMinutes(15);

        // Get all active schedules where next_due_at is at or before current time
        $dueSchedules = VitalsSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', $now)
            ->get();

        $dueCount = 0;
        $overdueCount = 0;

        foreach ($dueSchedules as $schedule) {
            // Check if this schedule already has an active alert
            $existingAlert = $schedule->alerts()
                ->whereIn('status', ['pending', 'due', 'overdue'])
                ->first();

            if ($existingAlert) {
                // Update existing alert status based on grace period
                if ($schedule->next_due_at <= $gracePeriodEnd) {
                    // Past grace period - mark as overdue
                    if ($existingAlert->status !== 'overdue') {
                        $alertService->updateAlertStatus($existingAlert, 'overdue');
                        $overdueCount++;
                    }
                } else {
                    // Within grace period - mark as due
                    if ($existingAlert->status !== 'due') {
                        $alertService->updateAlertStatus($existingAlert, 'due');
                        $dueCount++;
                    }
                }
            } else {
                // Create new alert
                $alert = $alertService->createAlert($schedule);

                // Set appropriate status based on grace period
                if ($schedule->next_due_at <= $gracePeriodEnd) {
                    $alertService->updateAlertStatus($alert, 'overdue');
                    $overdueCount++;
                } else {
                    $alertService->updateAlertStatus($alert, 'due');
                    $dueCount++;
                }
            }
        }

        $this->info("Processed {$dueSchedules->count()} schedules: {$dueCount} due, {$overdueCount} overdue");

        return Command::SUCCESS;
    }
}
