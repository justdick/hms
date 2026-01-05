<?php

namespace App\Policies;

use App\Models\MedicationAdministration;
use App\Models\User;

/**
 * Policy for Medication Administration (MAR)
 *
 * Simplified policy for on-demand medication recording.
 */
class MedicationAdministrationPolicy
{
    /**
     * Determine whether the user can view any medication administrations.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('medications.view');
    }

    /**
     * Determine whether the user can view a specific medication administration.
     */
    public function view(User $user, MedicationAdministration $medicationAdministration): bool
    {
        return $user->can('medications.view');
    }

    /**
     * Determine whether the user can create (record) a medication administration.
     */
    public function create(User $user): bool
    {
        return $user->can('medications.administer');
    }

    /**
     * Determine whether the user can delete medication administrations.
     * Can only delete within 3 days of recording.
     */
    public function delete(User $user, MedicationAdministration $medicationAdministration): bool
    {
        if (! $user->can('medications.delete')) {
            return false;
        }

        // Can only delete if recorded within the last 3 days
        if ($medicationAdministration->administered_at) {
            return $medicationAdministration->administered_at >= now()->subDays(3);
        }

        return true;
    }
}
