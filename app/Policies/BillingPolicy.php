<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\User;

class BillingPolicy
{
    /**
     * Determine whether the user can view any billing records.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view-all') || $user->can('billing.view-dept');
    }

    /**
     * Determine whether the user can create billing records.
     */
    public function create(User $user): bool
    {
        return $user->can('billing.create');
    }

    /**
     * Determine whether the user can waive a charge.
     */
    public function waive(User $user, Charge $charge): bool
    {
        // Only allow waiving pending charges
        if (! $charge->isPending()) {
            return false;
        }

        return $user->can('billing.waive-charges');
    }

    /**
     * Determine whether the user can adjust a charge.
     */
    public function adjust(User $user, Charge $charge): bool
    {
        // Only allow adjusting pending charges
        if (! $charge->isPending()) {
            return false;
        }

        return $user->can('billing.adjust-charges');
    }

    /**
     * Determine whether the user can override service access requirements.
     */
    public function overrideService(User $user, PatientCheckin $checkin): bool
    {
        return $user->can('billing.emergency-override');
    }

    /**
     * Determine whether the user can cancel a charge.
     */
    public function cancel(User $user, Charge $charge): bool
    {
        // Only allow cancelling pending charges
        if (! $charge->isPending()) {
            return false;
        }

        return $user->can('billing.cancel-charges');
    }

    /**
     * Determine whether the user can view the audit trail.
     */
    public function viewAuditTrail(User $user): bool
    {
        return $user->can('billing.view-audit-trail');
    }
}
