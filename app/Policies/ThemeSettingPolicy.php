<?php

namespace App\Policies;

use App\Models\ThemeSetting;
use App\Models\User;

class ThemeSettingPolicy
{
    /**
     * Determine whether the user can view any models (access theme settings page).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view-theme') || $user->can('settings.manage-theme');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->can('settings.view-theme') || $user->can('settings.manage-theme');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('settings.manage-theme');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->can('settings.manage-theme');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->can('settings.manage-theme');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->can('settings.manage-theme');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ThemeSetting $themeSetting): bool
    {
        return $user->can('settings.manage-theme');
    }
}
