<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * The protected admin username that cannot be deleted or deactivated.
     */
    public const PROTECTED_ADMIN_USERNAME = 'admin';

    /**
     * Check if the target user is the protected admin account.
     */
    private function isProtectedAdmin(User $model): bool
    {
        return $model->username === self::PROTECTED_ADMIN_USERNAME;
    }

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view-all') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view-all') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users.update') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can toggle the active status of the model.
     * Users cannot deactivate themselves or the protected admin account.
     */
    public function toggleActive(User $user, User $model): bool
    {
        // Prevent self-deactivation
        if ($user->id === $model->id) {
            return false;
        }

        // Prevent deactivating the protected admin account
        if ($this->isProtectedAdmin($model)) {
            return false;
        }

        return $user->can('users.update') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can reset the password of the model.
     * Users cannot reset their own password via admin panel - they should use profile settings.
     */
    public function resetPassword(User $user, User $model): bool
    {
        // Prevent self-password-reset via admin panel
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.reset-password') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can delete the model.
     * Users cannot delete themselves or the protected admin account.
     */
    public function delete(User $user, User $model): bool
    {
        // Prevent self-deletion
        if ($user->id === $model->id) {
            return false;
        }

        // Prevent deleting the protected admin account
        if ($this->isProtectedAdmin($model)) {
            return false;
        }

        return $user->can('users.delete') || $user->hasRole('Admin');
    }
}
