<?php

namespace App\Services;

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalsSchedule;
use Carbon\Carbon;

class VitalsScheduleService
{
    /**
     * Create a new vitals schedule for a patient admission.
     */
    public function createSchedule(
        PatientAdmission $admission,
        int $intervalMinutes,
        User $createdBy
    ): VitalsSchedule {
        // Validate interval is within acceptable range (15 minutes to 24 hours)
        if ($intervalMinutes < 15 || $intervalMinutes > 1440) {
            throw new \InvalidArgumentException('Interval must be between 15 and 1440 minutes');
        }

        // Check if patient is still admitted
        if ($admission->status !== 'admitted') {
            throw new \InvalidArgumentException('Cannot create schedule for non-admitted patient');
        }

        // Disable any existing active schedules for this admission
        VitalsSchedule::where('patient_admission_id', $admission->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Calculate next due time from now
        $nextDueAt = $this->calculateNextDueTime(
            VitalsSchedule::make(['interval_minutes' => $intervalMinutes]),
            now()
        );

        return VitalsSchedule::create([
            'patient_admission_id' => $admission->id,
            'interval_minutes' => $intervalMinutes,
            'next_due_at' => $nextDueAt,
            'last_recorded_at' => null,
            'is_active' => true,
            'created_by' => $createdBy->id,
        ]);
    }

    /**
     * Update an existing vitals schedule interval.
     */
    public function updateSchedule(
        VitalsSchedule $schedule,
        int $intervalMinutes
    ): VitalsSchedule {
        // Validate interval is within acceptable range
        if ($intervalMinutes < 15 || $intervalMinutes > 1440) {
            throw new \InvalidArgumentException('Interval must be between 15 and 1440 minutes');
        }

        $schedule->interval_minutes = $intervalMinutes;

        // Recalculate next due time based on last recorded time or now
        $baseTime = $schedule->last_recorded_at ?? now();
        $schedule->next_due_at = $this->calculateNextDueTime($schedule, $baseTime);

        $schedule->save();

        return $schedule;
    }

    /**
     * Disable a vitals schedule.
     */
    public function disableSchedule(VitalsSchedule $schedule): void
    {
        $schedule->is_active = false;
        $schedule->save();

        // Dismiss any pending alerts
        $schedule->alerts()
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->update(['status' => 'dismissed']);
    }

    /**
     * Calculate the next due time based on interval.
     */
    public function calculateNextDueTime(
        VitalsSchedule $schedule,
        Carbon $fromTime
    ): Carbon {
        return $fromTime->copy()->addMinutes($schedule->interval_minutes);
    }

    /**
     * Record that vitals have been completed and update schedule.
     */
    public function recordVitalsCompleted(
        VitalsSchedule $schedule,
        VitalSign $vitalSign
    ): void {
        $schedule->last_recorded_at = $vitalSign->recorded_at;
        $schedule->next_due_at = $this->calculateNextDueTime($schedule, $vitalSign->recorded_at);
        $schedule->save();

        // Mark any active alerts as completed
        $schedule->alerts()
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->update(['status' => 'completed']);
    }

    /**
     * Get the current status of a schedule with time calculations.
     */
    public function getScheduleStatus(VitalsSchedule $schedule): array
    {
        $status = $schedule->getCurrentStatus();
        $timeUntilDue = $schedule->getTimeUntilDue();
        $timeOverdue = $schedule->getTimeOverdue();

        return [
            'status' => $status,
            'next_due_at' => $schedule->next_due_at?->toIso8601String(),
            'time_until_due_minutes' => $timeUntilDue,
            'time_overdue_minutes' => $timeOverdue,
            'interval_minutes' => $schedule->interval_minutes,
            'last_recorded_at' => $schedule->last_recorded_at?->toIso8601String(),
            'is_active' => $schedule->is_active,
        ];
    }
}
