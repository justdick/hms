<?php

namespace App\Policies;

use App\Models\NursingNote;
use App\Models\User;

class NursingNotePolicy
{
    /**
     * Determine whether the user can view any nursing notes.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('nursing-notes.view') ||
               $user->hasRole(['Admin', 'Nurse', 'Doctor']);
    }

    /**
     * Determine whether the user can view the nursing note.
     */
    public function view(User $user, NursingNote $nursingNote): bool
    {
        return $user->can('nursing-notes.view') ||
               $user->hasRole(['Admin', 'Nurse', 'Doctor']);
    }

    /**
     * Determine whether the user can create nursing notes.
     */
    public function create(User $user): bool
    {
        return $user->can('nursing-notes.create') ||
               $user->hasRole(['Admin', 'Nurse']);
    }

    /**
     * Determine whether the user can update the nursing note.
     */
    public function update(User $user, NursingNote $nursingNote): bool
    {
        // Admin can update all notes
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Nurses can only update their own notes within 24 hours
        if ($user->hasRole('Nurse') || $user->can('nursing-notes.update')) {
            return $nursingNote->nurse_id === $user->id &&
                   $nursingNote->created_at->greaterThan(now()->subHours(24));
        }

        return false;
    }

    /**
     * Determine whether the user can delete the nursing note.
     */
    public function delete(User $user, NursingNote $nursingNote): bool
    {
        // Admin can delete all notes
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Nurses can only delete their own notes within 2 hours
        if ($user->hasRole('Nurse') || $user->can('nursing-notes.delete')) {
            return $nursingNote->nurse_id === $user->id &&
                   $nursingNote->created_at->greaterThan(now()->subHours(2));
        }

        return false;
    }

    /**
     * Determine whether the user can restore the nursing note.
     */
    public function restore(User $user, NursingNote $nursingNote): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can permanently delete the nursing note.
     */
    public function forceDelete(User $user, NursingNote $nursingNote): bool
    {
        return $user->hasRole('Admin');
    }
}
