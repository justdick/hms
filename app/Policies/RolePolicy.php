<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Protected roles that cannot be edited or deleted.
     */
    public const PROTECTED_ROLES = ['Admin'];

    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view-all') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view-all') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the role.
     * Admin role cannot be updated.
     */
    public function update(User $user, Role $role): bool
    {
        // Protected roles cannot be edited
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return false;
        }

        return $user->can('roles.update') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can delete the role.
     * Deletion is blocked if the role has users assigned or is protected.
     */
    public function delete(User $user, Role $role): bool
    {
        // Protected roles cannot be deleted
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            return false;
        }

        // Check if role has users assigned
        if ($role->users()->count() > 0) {
            return false;
        }

        return $user->can('roles.delete') || $user->hasRole('Admin');
    }
}
