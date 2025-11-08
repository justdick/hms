<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Prescription $prescription): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Prescription $prescription): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Prescription $prescription): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Prescription $prescription): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Prescription $prescription): bool
    {
        return false;
    }

    /**
     * Determine whether the user can configure a medication schedule.
     */
    public function configureSchedule(User $user, Prescription $prescription): bool
    {
        // Must have permission to manage prescriptions
        if (! $user->can('manage prescriptions')) {
            return false;
        }

        // Cannot configure schedule for discontinued prescriptions
        if ($prescription->isDiscontinued()) {
            return false;
        }

        // Cannot configure schedule for PRN medications
        if (strtoupper($prescription->frequency) === 'PRN') {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reconfigure an existing medication schedule.
     */
    public function reconfigureSchedule(User $user, Prescription $prescription): bool
    {
        // Must have permission to manage prescriptions
        if (! $user->can('manage prescriptions')) {
            return false;
        }

        // Cannot reconfigure schedule for discontinued prescriptions
        if ($prescription->isDiscontinued()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can discontinue a prescription.
     */
    public function discontinue(User $user, Prescription $prescription): bool
    {
        // Must have permission to manage prescriptions
        if (! $user->can('manage prescriptions')) {
            return false;
        }

        // Cannot discontinue a prescription that is already discontinued
        if ($prescription->isDiscontinued()) {
            return false;
        }

        return true;
    }
}
