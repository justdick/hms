<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view') ||
               $user->can('patients.view-all') ||
               $user->can('patients.view-dept') ||
               $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Patient $patient): bool
    {
        // Admin can view all
        if ($user->hasRole('Admin') || $user->can('patients.view-all') || $user->can('patients.view')) {
            return true;
        }

        // Department-based access (if patient has department associations)
        if ($user->can('patients.view-dept')) {
            // For now, allow department users to view all patients
            // This could be refined later with patient-department relationships
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('patients.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Patient $patient): bool
    {
        // Admin can update all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have update permission
        return $user->can('patients.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Patient $patient): bool
    {
        // Admin can delete all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have delete permission
        return $user->can('patients.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view medical history.
     */
    public function viewMedicalHistory(User $user, Patient $patient): bool
    {
        // Admin and doctors can view medical history
        if ($user->hasRole('Admin') || $user->hasRole('Doctor')) {
            return true;
        }

        // Users with explicit permission
        return $user->can('patients.view-medical-history');
    }
}
