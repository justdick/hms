<?php

namespace App\Policies;

use App\Models\LabService;
use App\Models\User;

class LabServicePolicy
{
    /**
     * Determine whether the user can configure test parameters.
     */
    public function configureParameters(User $user): bool
    {
        return $user->hasPermissionTo('configure lab parameters');
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('lab-services.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LabService $labService): bool
    {
        return $user->hasPermissionTo('lab-services.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('lab-services.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LabService $labService): bool
    {
        return $user->hasPermissionTo('lab-services.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LabService $labService): bool
    {
        return $user->hasPermissionTo('lab-services.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LabService $labService): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LabService $labService): bool
    {
        return false;
    }
}
