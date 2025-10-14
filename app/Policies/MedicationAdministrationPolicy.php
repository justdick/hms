<?php

namespace App\Policies;

use App\Models\MedicationAdministration;
use App\Models\User;

class MedicationAdministrationPolicy
{
    /**
     * Determine whether the user can view any medication administrations.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view medication administrations');
    }

    /**
     * Determine whether the user can view a specific medication administration.
     */
    public function view(User $user, MedicationAdministration $medicationAdministration): bool
    {
        return $user->can('view medication administrations');
    }

    /**
     * Determine whether the user can administer (update) medications.
     * Can only administer scheduled medications that are due.
     */
    public function administer(User $user, MedicationAdministration $medicationAdministration): bool
    {
        // Must have permission to administer medications
        if (! $user->can('administer medications')) {
            return false;
        }

        // Can only administer scheduled medications
        if ($medicationAdministration->status !== 'scheduled') {
            return false;
        }

        // Can only administer if scheduled time has passed or is within 30 minutes
        $scheduledTime = $medicationAdministration->scheduled_time;
        $now = now();

        return $scheduledTime <= $now->copy()->addMinutes(30);
    }

    /**
     * Determine whether the user can hold a medication.
     */
    public function hold(User $user, MedicationAdministration $medicationAdministration): bool
    {
        // Must have permission to administer medications
        if (! $user->can('administer medications')) {
            return false;
        }

        // Can only hold scheduled medications
        return $medicationAdministration->status === 'scheduled';
    }

    /**
     * Determine whether the user can mark a medication as refused.
     */
    public function refuse(User $user, MedicationAdministration $medicationAdministration): bool
    {
        // Must have permission to administer medications
        if (! $user->can('administer medications')) {
            return false;
        }

        // Can only refuse scheduled medications
        return $medicationAdministration->status === 'scheduled';
    }

    /**
     * Determine whether the user can delete medication administrations.
     * Can only delete within 2 hours of administration.
     */
    public function delete(User $user, MedicationAdministration $medicationAdministration): bool
    {
        if (! $user->can('delete medication administrations')) {
            return false;
        }

        // Can only delete if administered within the last 2 hours
        if ($medicationAdministration->administered_at) {
            return $medicationAdministration->administered_at >= now()->subHours(2);
        }

        // Can delete scheduled medications
        return $medicationAdministration->status === 'scheduled';
    }
}
