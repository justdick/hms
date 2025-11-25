<?php

namespace App\Policies;

use App\Models\ConsultationProcedure;
use App\Models\User;

class ConsultationProcedurePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('consultations.view-all')
            || $user->can('consultations.view-dept')
            || $user->can('consultations.view-own');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ConsultationProcedure $consultationProcedure): bool
    {
        // Same access as consultation
        return $user->can('consultations.view-all')
            || ($user->can('consultations.view-dept') && $user->departments->contains($consultationProcedure->consultation->patientCheckin->department_id))
            || ($user->can('consultations.view-own') && $consultationProcedure->doctor_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('consultations.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ConsultationProcedure $consultationProcedure): bool
    {
        // Can update if can update the consultation
        return $user->can('consultations.view-all')
            || ($user->can('consultations.view-dept') && $user->departments->contains($consultationProcedure->consultation->patientCheckin->department_id))
            || $consultationProcedure->doctor_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ConsultationProcedure $consultationProcedure): bool
    {
        // Only the doctor who documented it or admin can delete
        return $user->can('consultations.view-all')
            || $consultationProcedure->doctor_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ConsultationProcedure $consultationProcedure): bool
    {
        return $user->can('consultations.view-all');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ConsultationProcedure $consultationProcedure): bool
    {
        return $user->can('consultations.view-all');
    }
}
