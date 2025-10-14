<?php

namespace App\Policies;

use App\Models\Drug;
use App\Models\User;

class DrugPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('drugs.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Drug $drug): bool
    {
        return $user->hasPermissionTo('drugs.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('drugs.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Drug $drug): bool
    {
        return $user->hasPermissionTo('drugs.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Drug $drug): bool
    {
        return $user->hasPermissionTo('drugs.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Drug $drug): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Drug $drug): bool
    {
        return false;
    }

    /**
     * Determine whether the user can manage drug batches.
     */
    public function manageBatches(User $user): bool
    {
        return $user->hasPermissionTo('drugs.manage-batches');
    }
}
