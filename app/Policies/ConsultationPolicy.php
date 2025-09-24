<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    /**
     * Determine whether the user can view any consultations.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('consultations.view-all') ||
               $user->can('consultations.view-dept') ||
               $user->can('consultations.view-own') ||
               $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the consultation.
     */
    public function view(User $user, Consultation $consultation): bool
    {
        // Admin can view all
        if ($user->hasRole('Admin') || $user->can('consultations.view-all')) {
            return true;
        }

        // Own consultations
        if ($user->can('consultations.view-own') && $consultation->doctor_id === $user->id) {
            return true;
        }

        // Department-based access
        if ($user->can('consultations.view-dept')) {
            return $user->departments->contains($consultation->patientCheckin->department_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create consultations.
     */
    public function create(User $user): bool
    {
        return $user->can('consultations.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the consultation.
     */
    public function update(User $user, Consultation $consultation): bool
    {
        // Admin can update all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Can update any consultation in their departments
        if ($user->can('consultations.update-any') &&
            $user->departments->contains($consultation->patientCheckin->department_id)) {
            return true;
        }

        // Can update only their own consultations
        if ($user->can('consultations.update-own') &&
            $consultation->doctor_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can complete the consultation.
     */
    public function complete(User $user, Consultation $consultation): bool
    {
        // Admin can complete all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have complete permission
        if (! $user->can('consultations.complete')) {
            return false;
        }

        // Any doctor in the department can complete consultations
        return $user->departments->contains($consultation->patientCheckin->department_id);
    }

    /**
     * Determine whether the user can delete the consultation.
     */
    public function delete(User $user, Consultation $consultation): bool
    {
        // Admin can delete all
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Must have delete permission
        if (! $user->can('consultations.delete')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($consultation->patientCheckin->department_id);
    }
}
