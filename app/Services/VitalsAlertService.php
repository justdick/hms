<?php

namespace App\Services;

use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use Illuminate\Support\Collection;

class VitalsAlertService
{
    /**
     * Check for schedules that are now due (within current time).
     */
    public function checkDueAlerts(): Collection
    {
        return VitalsSchedule::query()
            ->where('is_active', true)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', now())
            ->with(['patientAdmission.patient', 'patientAdmission.bed'])
            ->get();
    }

    /**
     * Check for alerts that are past the 15-minute grace period.
     */
    public function checkOverdueAlerts(): Collection
    {
        $gracePeriodEnd = now()->subMinutes(15);

        return VitalsAlert::query()
            ->whereIn('status', ['pending', 'due'])
            ->where('due_at', '<=', $gracePeriodEnd)
            ->with(['vitalsSchedule', 'patientAdmission.patient', 'patientAdmission.bed'])
            ->get();
    }

    /**
     * Create a new alert for a schedule.
     */
    public function createAlert(VitalsSchedule $schedule): VitalsAlert
    {
        // Check if there's already an active alert for this schedule
        $existingAlert = VitalsAlert::query()
            ->where('vitals_schedule_id', $schedule->id)
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->first();

        if ($existingAlert) {
            // Update existing alert status based on time
            $this->updateAlertStatusBasedOnTime($existingAlert);

            return $existingAlert;
        }

        // Determine initial status based on current time
        $now = now();
        $gracePeriodEnd = $schedule->next_due_at->copy()->addMinutes(15);

        $status = 'pending';
        if ($now->greaterThanOrEqualTo($gracePeriodEnd)) {
            $status = 'overdue';
        } elseif ($now->greaterThanOrEqualTo($schedule->next_due_at)) {
            $status = 'due';
        }

        return VitalsAlert::create([
            'vitals_schedule_id' => $schedule->id,
            'patient_admission_id' => $schedule->patient_admission_id,
            'due_at' => $schedule->next_due_at,
            'status' => $status,
        ]);
    }

    /**
     * Update alert status based on current time.
     */
    private function updateAlertStatusBasedOnTime(VitalsAlert $alert): void
    {
        $now = now();
        $gracePeriodEnd = $alert->due_at->copy()->addMinutes(15);

        if ($now->greaterThanOrEqualTo($gracePeriodEnd) && $alert->status !== 'overdue') {
            $alert->markAsOverdue();
        } elseif ($now->greaterThanOrEqualTo($alert->due_at) && $alert->status === 'pending') {
            $alert->markAsDue();
        }
    }

    /**
     * Update the status of an alert.
     */
    public function updateAlertStatus(VitalsAlert $alert, string $status): void
    {
        $validStatuses = ['pending', 'due', 'overdue', 'completed', 'dismissed'];

        if (! in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $alert->status = $status;
        $alert->save();
    }

    /**
     * Get all active alerts for a specific ward.
     */
    public function getActiveAlertsForWard(Ward $ward): Collection
    {
        $alerts = VitalsAlert::query()
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->whereHas('patientAdmission', function ($query) use ($ward) {
                $query->where('ward_id', $ward->id)
                    ->where('status', 'admitted');
            })
            ->with([
                'patientAdmission.patient',
                'patientAdmission.bed',
                'vitalsSchedule',
            ])
            ->orderBy('due_at')
            ->get();

        // Sort by urgency: overdue, due, pending
        return $alerts->sortBy(function ($alert) {
            return match ($alert->status) {
                'overdue' => 1,
                'due' => 2,
                'pending' => 3,
                default => 4,
            };
        })->values();
    }

    /**
     * Get all active alerts for a specific user (based on their assigned wards).
     */
    public function getActiveAlertsForUser(User $user): Collection
    {
        // Get all wards the user has access to
        // This assumes users have a relationship with wards or permissions
        // For now, we'll return all active alerts
        // This can be refined based on actual user-ward relationships

        $alerts = VitalsAlert::query()
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->whereHas('patientAdmission', function ($query) {
                $query->where('status', 'admitted');
            })
            ->with([
                'patientAdmission.patient',
                'patientAdmission.bed',
                'patientAdmission.ward',
                'vitalsSchedule',
            ])
            ->orderBy('due_at')
            ->get();

        // Sort by urgency: overdue, due, pending
        return $alerts->sortBy(function ($alert) {
            return match ($alert->status) {
                'overdue' => 1,
                'due' => 2,
                'pending' => 3,
                default => 4,
            };
        })->values();
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(VitalsAlert $alert, User $user): void
    {
        $alert->acknowledge($user);
    }

    /**
     * Dismiss an alert.
     */
    public function dismissAlert(VitalsAlert $alert, User $user): void
    {
        $alert->acknowledge($user);
        $alert->status = 'dismissed';
        $alert->save();
    }
}
