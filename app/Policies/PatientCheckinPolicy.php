<?php

namespace App\Policies;

use App\Models\PatientCheckin;
use App\Models\User;

class PatientCheckinPolicy
{
    /**
     * Determine whether the user can view any patient check-ins.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('checkins.view-all') ||
               $user->can('checkins.view-dept') ||
               $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view check-ins from any date (historical).
     */
    public function viewAnyDate(User $user): bool
    {
        return $user->can('checkins.view-any-date') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view check-ins from other departments.
     */
    public function viewAnyDepartment(User $user): bool
    {
        return $user->can('checkins.view-any-department') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the patient check-in.
     */
    public function view(User $user, PatientCheckin $patientCheckin): bool
    {
        // Admin can view all
        if ($user->hasRole('Admin') || $user->can('checkins.view-all')) {
            return true;
        }

        // Department-based access
        if ($user->can('checkins.view-dept')) {
            return $user->departments->contains($patientCheckin->department_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create patient check-ins.
     */
    public function create(User $user): bool
    {
        return $user->can('checkins.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the patient check-in.
     */
    public function update(User $user, PatientCheckin $patientCheckin): bool
    {
        // Admin can update all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have update permission
        if (! $user->can('checkins.update')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($patientCheckin->department_id);
    }

    /**
     * Determine whether the user can delete the patient check-in.
     */
    public function delete(User $user, PatientCheckin $patientCheckin): bool
    {
        // Admin can delete all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have delete permission
        if (! $user->can('checkins.delete')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($patientCheckin->department_id);
    }

    /**
     * Determine whether the user can cancel the patient check-in.
     */
    public function cancel(User $user, PatientCheckin $patientCheckin): bool
    {
        // Admin can cancel all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have cancel permission (or update permission as fallback)
        if (! $user->can('checkins.cancel') && ! $user->can('checkins.update')) {
            return false;
        }

        // Cannot cancel completed or already cancelled check-ins
        if ($patientCheckin->isCompleted() || $patientCheckin->isCancelled()) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($patientCheckin->department_id);
    }

    /**
     * Determine whether the user can update the check-in date.
     * This is a special permission that allows editing dates even for completed check-ins.
     */
    public function updateDate(User $user, PatientCheckin $patientCheckin): bool
    {
        // Admin can update dates
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have specific date update permission
        if (! $user->can('checkins.update-date')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($patientCheckin->department_id);
    }
}
