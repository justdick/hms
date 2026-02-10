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
     * Users with medications.delete can delete within 3 days.
     * Users with medications.delete-old can delete records older than 3 days.
     */
    public function delete(User $user, MedicationAdministration $medicationAdministration): bool
    {
        $isOld = $medicationAdministration->administered_at
            && $medicationAdministration->administered_at < now()->subDays(3);

        if ($isOld) {
            return $user->can('medications.delete-old');
        }

        return $user->can('medications.delete');
    }
}
