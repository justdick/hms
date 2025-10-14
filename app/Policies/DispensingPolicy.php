<?php

namespace App\Policies;

use App\Models\Dispensing;
use App\Models\Prescription;
use App\Models\User;

class DispensingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('dispensing.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Dispensing $dispensing): bool
    {
        return $user->hasPermissionTo('dispensing.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('dispensing.process');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Dispensing $dispensing): bool
    {
        return $user->hasPermissionTo('dispensing.process');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Dispensing $dispensing): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Dispensing $dispensing): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Dispensing $dispensing): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view dispensing history.
     */
    public function viewHistory(User $user): bool
    {
        return $user->hasPermissionTo('dispensing.history');
    }

    /**
     * Determine whether the user can review prescriptions (Touchpoint 1).
     */
    public function review(User $user, Prescription $prescription): bool
    {
        return $user->hasPermissionTo('dispensing.review');
    }

    /**
     * Determine whether the user can adjust prescription quantities.
     */
    public function adjustQuantity(User $user, Prescription $prescription): bool
    {
        return $user->hasPermissionTo('dispensing.adjust-quantity');
    }

    /**
     * Determine whether the user can mark prescriptions as external.
     */
    public function markExternal(User $user, Prescription $prescription): bool
    {
        return $user->hasPermissionTo('dispensing.mark-external');
    }

    /**
     * Determine whether the user can dispense prescriptions (Touchpoint 2).
     */
    public function dispense(User $user, Prescription $prescription): bool
    {
        return $user->hasPermissionTo('dispensing.process');
    }

    /**
     * Determine whether the user can partially dispense prescriptions.
     */
    public function partialDispense(User $user, Prescription $prescription): bool
    {
        return $user->hasPermissionTo('dispensing.partial');
    }

    /**
     * Determine whether the user can override payment requirements.
     */
    public function overridePayment(User $user, Prescription $prescription): bool
    {
        return $user->hasPermissionTo('dispensing.override-payment');
    }
}
