<?php

namespace App\Policies;

use App\Models\PatientAdmission;
use App\Models\User;

class PatientAdmissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('admissions.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PatientAdmission $patientAdmission): bool
    {
        return $user->can('admissions.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('admissions.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PatientAdmission $patientAdmission): bool
    {
        return $user->can('admissions.update');
    }

    /**
     * Determine whether the user can discharge the patient.
     */
    public function discharge(User $user, PatientAdmission $patientAdmission): bool
    {
        return $user->can('admissions.discharge');
    }

    /**
     * Determine whether the user can transfer the patient to another ward.
     */
    public function transfer(User $user, PatientAdmission $patientAdmission): bool
    {
        return $user->can('admissions.transfer');
    }

    /**
     * Determine whether the user can view transfer history.
     */
    public function viewTransfers(User $user, PatientAdmission $patientAdmission): bool
    {
        return $user->can('admissions.view-transfers') || $user->can('admissions.transfer');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PatientAdmission $patientAdmission): bool
    {
        return false; // Admissions should not be deleted
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PatientAdmission $patientAdmission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PatientAdmission $patientAdmission): bool
    {
        return false;
    }
}
