<?php

namespace App\Policies;

use App\Models\MinorProcedureType;
use App\Models\User;

class MinorProcedureTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('minor-procedures.view-types');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MinorProcedureType $minorProcedureType): bool
    {
        return $user->can('minor-procedures.view-types');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('minor-procedures.create-types');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MinorProcedureType $minorProcedureType): bool
    {
        return $user->can('minor-procedures.update-types');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MinorProcedureType $minorProcedureType): bool
    {
        return $user->can('minor-procedures.delete-types');
    }
}
