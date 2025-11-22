<?php

namespace App\Policies;

use App\Models\MinorProcedure;
use App\Models\User;

class MinorProcedurePolicy
{
    /**
     * Determine whether the user can view any minor procedures.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('minor-procedures.view-dept') ||
               $user->can('minor-procedures.view-all') ||
               $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the minor procedure.
     */
    public function view(User $user, MinorProcedure $minorProcedure): bool
    {
        // Admin can view all
        if ($user->hasRole('Admin') || $user->can('minor-procedures.view-all')) {
            return true;
        }

        // Department-based access
        if ($user->can('minor-procedures.view-dept')) {
            return $user->departments->contains($minorProcedure->patientCheckin->department_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create minor procedures.
     */
    public function create(User $user): bool
    {
        return $user->can('minor-procedures.perform') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the minor procedure.
     */
    public function update(User $user, MinorProcedure $minorProcedure): bool
    {
        // Admin can update all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Cannot update completed procedures
        if ($minorProcedure->status === 'completed') {
            return false;
        }

        // Must have perform permission
        if (! $user->can('minor-procedures.perform')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($minorProcedure->patientCheckin->department_id);
    }

    /**
     * Determine whether the user can delete the minor procedure.
     */
    public function delete(User $user, MinorProcedure $minorProcedure): bool
    {
        // Admin can delete all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Cannot delete completed procedures
        if ($minorProcedure->status === 'completed') {
            return false;
        }

        // Must have perform permission
        if (! $user->can('minor-procedures.perform')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($minorProcedure->patientCheckin->department_id);
    }

    /**
     * Determine whether the user can restore the minor procedure.
     */
    public function restore(User $user, MinorProcedure $minorProcedure): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can permanently delete the minor procedure.
     */
    public function forceDelete(User $user, MinorProcedure $minorProcedure): bool
    {
        return $user->hasRole('Admin');
    }
}
