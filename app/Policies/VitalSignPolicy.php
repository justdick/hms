<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VitalSign;

class VitalSignPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('vitals.view-all') ||
               $user->can('vitals.view-dept') ||
               $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VitalSign $vitalSign): bool
    {
        // Admin can view all
        if ($user->hasRole('Admin') || $user->can('vitals.view-all')) {
            return true;
        }

        // Department-based access
        if ($user->can('vitals.view-dept')) {
            return true; // For now, allow department users to view all vitals
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('vitals.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VitalSign $vitalSign): bool
    {
        // Admin can update all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have update permission
        return $user->can('vitals.update');
    }

    /**
     * Determine whether the user can edit the recorded timestamp.
     */
    public function editTimestamp(User $user, VitalSign $vitalSign): bool
    {
        // Admin can always edit timestamps
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have specific timestamp edit permission
        return $user->can('vitals.edit-timestamp');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VitalSign $vitalSign): bool
    {
        // Admin can delete all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have delete permission
        return $user->can('vitals.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VitalSign $vitalSign): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VitalSign $vitalSign): bool
    {
        return false;
    }
}
