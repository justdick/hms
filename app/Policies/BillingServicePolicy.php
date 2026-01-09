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
        return $user->hasRole('Admin') || $this->hasPermissionSafe($user, 'billing.view-all');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BillingService $billingService): bool
    {
        return $user->hasRole('Admin') || $this->hasPermissionSafe($user, 'billing.view-all');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BillingService $billingService): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BillingService $billingService): bool
    {
        return $user->hasRole('Admin');
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

    /**
     * Safely check if user has permission without throwing exception.
     */
    private function hasPermissionSafe(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }
}
