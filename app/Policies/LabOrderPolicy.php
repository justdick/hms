<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\User;

class LabOrderPolicy
{
    /**
     * Determine whether the user can view any lab orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('lab-orders.view-all') ||
               $user->can('lab-orders.view-dept') ||
               $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the lab order.
     */
    public function view(User $user, LabOrder $labOrder): bool
    {
        // Admin can view all
        if ($user->hasRole('Admin') || $user->can('lab-orders.view-all')) {
            return true;
        }

        // Department-based access
        if ($user->can('lab-orders.view-dept')) {
            return $user->departments->contains($labOrder->consultation->patientCheckin->department_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create lab orders for a consultation.
     */
    public function create(User $user, Consultation $consultation): bool
    {
        // Admin can create for any consultation
        if ($user->hasRole('Admin') || $user->can('lab-orders.view-all')) {
            return true;
        }

        // Must have create permission
        if (! $user->can('lab-orders.create')) {
            return false;
        }

        // Must be assigned to the consultation's department
        return $user->departments->contains($consultation->patientCheckin->department_id);
    }

    /**
     * Determine whether the user can update the lab order.
     */
    public function update(User $user, LabOrder $labOrder): bool
    {
        // Admin can update all
        if ($user->hasRole('Admin') || $user->can('lab-orders.view-all')) {
            return true;
        }

        // Must have update permission
        if (! $user->can('lab-orders.update')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($labOrder->consultation->patientCheckin->department_id);
    }

    /**
     * Determine whether the user can delete/cancel the lab order.
     */
    public function delete(User $user, LabOrder $labOrder): bool
    {
        // Admin can delete all
        if ($user->hasRole('Admin') || $user->can('lab-orders.view-all')) {
            return true;
        }

        // Must have delete permission
        if (! $user->can('lab-orders.delete')) {
            return false;
        }

        // Department-based access
        return $user->departments->contains($labOrder->consultation->patientCheckin->department_id);
    }
}
