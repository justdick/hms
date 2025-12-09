<?php

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

class BackupPolicy
{
    /**
     * Determine whether the user can view any backups.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('backups.view') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can view the backup.
     */
    public function view(User $user, Backup $backup): bool
    {
        return $user->can('backups.view') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can create backups.
     */
    public function create(User $user): bool
    {
        return $user->can('backups.create') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can delete the backup.
     */
    public function delete(User $user, Backup $backup): bool
    {
        return $user->can('backups.delete') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can restore from the backup.
     */
    public function restore(User $user, Backup $backup): bool
    {
        return $user->can('backups.restore') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can manage backup settings.
     */
    public function manageSettings(User $user): bool
    {
        return $user->can('backups.manage-settings') || $user->hasRole('Admin');
    }
}
