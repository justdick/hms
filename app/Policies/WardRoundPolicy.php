<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WardRound;

class WardRoundPolicy
{
    /**
     * Determine whether the user can view any ward rounds.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ward_rounds.view');
    }

    /**
     * Determine whether the user can view the ward round.
     */
    public function view(User $user, WardRound $wardRound): bool
    {
        return $user->can('ward_rounds.view');
    }

    /**
     * Determine whether the user can create ward rounds.
     */
    public function create(User $user): bool
    {
        return $user->can('ward_rounds.create');
    }

    /**
     * Determine whether the user can update the ward round.
     */
    public function update(User $user, WardRound $wardRound): bool
    {
        if (! $user->can('ward_rounds.update')) {
            return false;
        }

        // Users can only update their own rounds within 24 hours
        // This ensures medical records maintain integrity after initial recording period
        return $wardRound->doctor_id === $user->id &&
               $wardRound->created_at->greaterThan(now()->subHours(24));
    }

    /**
     * Determine whether the user can delete the ward round.
     */
    public function delete(User $user, WardRound $wardRound): bool
    {
        // Ward rounds are medical records and should generally not be deleted
        // Only admins with specific permission can delete if absolutely necessary
        return $user->can('ward_rounds.delete');
    }

    /**
     * Determine whether the user can restore the ward round.
     */
    public function restore(User $user, WardRound $wardRound): bool
    {
        return $user->can('ward_rounds.restore');
    }

    /**
     * Determine whether the user can permanently delete the ward round.
     */
    public function forceDelete(User $user, WardRound $wardRound): bool
    {
        return $user->can('ward_rounds.force_delete');
    }
}
