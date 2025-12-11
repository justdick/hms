<?php

namespace App\Policies;

use App\Models\User;

class NhisSettingsPolicy
{
    /**
     * Determine if the user can view NHIS settings.
     */
    public function view(User $user): bool
    {
        return $user->can('nhis-settings.view')
            || $user->can('nhis-settings.manage')
            || $user->can('system.admin');
    }

    /**
     * Determine if the user can manage NHIS settings.
     */
    public function manage(User $user): bool
    {
        return $user->can('nhis-settings.manage')
            || $user->can('system.admin');
    }
}
