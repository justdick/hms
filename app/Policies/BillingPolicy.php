<?php

namespace App\Policies;

use App\Models\Charge;
use App\Models\Patient;
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
     * Determine whether the user can collect payments.
     */
    public function collect(User $user): bool
    {
        return $user->can('billing.collect');
    }

    /**
     * Determine whether the user can view all billing data (finance officer dashboard).
     */
    public function viewAll(User $user): bool
    {
        return $user->can('billing.view-all');
    }

    /**
     * Determine whether the user can create service overrides for patients.
     */
    public function override(User $user): bool
    {
        return $user->can('billing.override');
    }

    /**
     * Determine whether the user can perform cash reconciliation.
     */
    public function reconcile(User $user): bool
    {
        return $user->can('billing.reconcile');
    }

    /**
     * Determine whether the user can access financial reports.
     */
    public function viewReports(User $user): bool
    {
        return $user->can('billing.reports');
    }

    /**
     * Determine whether the user can generate patient statements.
     */
    public function generateStatements(User $user): bool
    {
        return $user->can('billing.statements');
    }

    /**
     * Determine whether the user can manage patient credit tags.
     */
    public function manageCredit(User $user, ?Patient $patient = null): bool
    {
        return $user->can('billing.manage-credit');
    }

    /**
     * Determine whether the user can void payments.
     */
    public function void(User $user, ?Charge $charge = null): bool
    {
        return $user->can('billing.void');
    }

    /**
     * Determine whether the user can process refunds.
     */
    public function refund(User $user, ?Charge $charge = null): bool
    {
        return $user->can('billing.refund');
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
