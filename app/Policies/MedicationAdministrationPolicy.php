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
     * Determine whether the user can create (record) a medication administration.
     */
    public function create(User $user): bool
    {
        return $user->can('administer medications');
    }

    /**
     * Determine whether the user can delete medication administrations.
     * Can only delete within 2 hours of recording.
     */
    public function delete(User $user, MedicationAdministration $medicationAdministration): bool
    {
        if (! $user->can('delete medication administrations')) {
            return false;
        }

        // Can only delete if recorded within the last 2 hours
        if ($medicationAdministration->administered_at) {
            return $medicationAdministration->administered_at >= now()->subHours(2);
        }

        return true;
    }
}
