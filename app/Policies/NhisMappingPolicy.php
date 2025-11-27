<?php

namespace App\Policies;

use App\Models\NhisItemMapping;
use App\Models\User;

class NhisMappingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('nhis-mappings.view') || $user->can('nhis-mappings.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NhisItemMapping $nhisMapping): bool
    {
        return $user->can('nhis-mappings.view') || $user->can('nhis-mappings.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('nhis-mappings.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NhisItemMapping $nhisMapping): bool
    {
        return $user->can('nhis-mappings.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NhisItemMapping $nhisMapping): bool
    {
        return $user->can('nhis-mappings.manage');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, NhisItemMapping $nhisMapping): bool
    {
        return $user->can('nhis-mappings.manage');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, NhisItemMapping $nhisMapping): bool
    {
        return $user->can('nhis-mappings.manage');
    }
}
