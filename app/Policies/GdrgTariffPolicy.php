<?php

namespace App\Policies;

use App\Models\GdrgTariff;
use App\Models\User;

class GdrgTariffPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('gdrg-tariffs.view') || $user->can('gdrg-tariffs.manage');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GdrgTariff $gdrgTariff): bool
    {
        return $user->can('gdrg-tariffs.view') || $user->can('gdrg-tariffs.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('gdrg-tariffs.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GdrgTariff $gdrgTariff): bool
    {
        return $user->can('gdrg-tariffs.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GdrgTariff $gdrgTariff): bool
    {
        return $user->can('gdrg-tariffs.manage');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GdrgTariff $gdrgTariff): bool
    {
        return $user->can('gdrg-tariffs.manage');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GdrgTariff $gdrgTariff): bool
    {
        return $user->can('gdrg-tariffs.manage');
    }
}
