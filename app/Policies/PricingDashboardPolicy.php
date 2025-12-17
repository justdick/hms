<?php

namespace App\Policies;

use App\Models\User;

class PricingDashboardPolicy
{
    /**
     * Determine whether the user can view the pricing dashboard.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pricing.view') || $user->can('billing.manage');
    }

    /**
     * Determine whether the user can update cash prices.
     */
    public function updateCashPrice(User $user): bool
    {
        return $user->can('pricing.edit') || $user->can('billing.manage');
    }

    /**
     * Determine whether the user can update insurance copay.
     */
    public function updateInsuranceCopay(User $user): bool
    {
        return $user->can('pricing.edit') || $user->can('billing.manage');
    }

    /**
     * Determine whether the user can update insurance coverage.
     */
    public function updateInsuranceCoverage(User $user): bool
    {
        return $user->can('pricing.edit') || $user->can('billing.manage');
    }

    /**
     * Determine whether the user can perform bulk updates.
     */
    public function bulkUpdate(User $user): bool
    {
        return $user->can('pricing.edit') || $user->can('billing.manage');
    }

    /**
     * Determine whether the user can export pricing data.
     */
    public function export(User $user): bool
    {
        return $user->can('pricing.view') || $user->can('billing.manage');
    }

    /**
     * Determine whether the user can import pricing data.
     */
    public function import(User $user): bool
    {
        return $user->can('pricing.edit') || $user->can('billing.manage');
    }
}
