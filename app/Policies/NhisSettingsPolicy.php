<?php

namespace App\Policies;

use App\Models\User;

class NhisSettingsPolicy
{
    /**
     * Determine if the user can manage NHIS settings.
     */
    public function manage(User $user): bool
    {
        return $user->can('settings.manage') || $user->can('admin.access');
    }
}
