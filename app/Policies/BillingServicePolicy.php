<?php

namespace App\Policies;

use App\Models\BillingService;
use App\Models\User;

class BillingServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('billing.view-all') || $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BillingService $billingService): bool
    {
        return $user->hasPermissionTo('billing.view-all') || $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BillingService $billingService): bool
    {
        return $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BillingService $billingService): bool
    {
        return $user->hasPermissionTo('system.admin');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BillingService $billingService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BillingService $billingService): bool
    {
        return false;
    }
}
