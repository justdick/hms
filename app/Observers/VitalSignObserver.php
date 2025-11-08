<?php

namespace App\Observers;

use App\Models\VitalSign;

class VitalSignObserver
{
    /**
     * Handle the VitalSign "created" event.
     * When vitals are recorded for an admission with an active schedule,
     * update the schedule to mark it as completed and calculate next due time.
     */
    public function created(VitalSign $vitalSign): void
    {
        // Only process if this vital sign is for an admission
        if (! $vitalSign->patient_admission_id) {
            return;
        }

        // Get the patient admission
        $admission = $vitalSign->patientAdmission;
        if (! $admission) {
            return;
        }

        // Check if there's an active vitals schedule
        $schedule = $admission->activeVitalsSchedule;
        if (! $schedule || ! $schedule->is_active) {
            return;
        }

        // Mark the schedule as completed with this vital sign
        $schedule->markAsCompleted($vitalSign);
    }
}
