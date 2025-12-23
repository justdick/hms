<?php

namespace App\Policies;

use App\Models\LabOrder;
use App\Models\User;

class RadiologyPolicy
{
    /**
     * Determine whether the user can view the radiology worklist.
     */
    public function viewWorklist(User $user): bool
    {
        return $user->can('radiology.view-worklist') || $user->hasRole('Admin');
    }

    /**
     * Determine whether the user can upload images for an imaging order.
     */
    public function uploadImages(User $user, ?LabOrder $labOrder = null): bool
    {
        // Must have the upload permission
        if (! $user->can('radiology.upload') && ! $user->hasRole('Admin')) {
            return false;
        }

        // If no specific order provided, just check permission
        if ($labOrder === null) {
            return true;
        }

        // Verify the order is an imaging order
        if (! $labOrder->labService || ! $labOrder->labService->is_imaging) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can enter a report for an imaging order.
     */
    public function enterReport(User $user, ?LabOrder $labOrder = null): bool
    {
        // Must have the report permission
        if (! $user->can('radiology.report') && ! $user->hasRole('Admin')) {
            return false;
        }

        // If no specific order provided, just check permission
        if ($labOrder === null) {
            return true;
        }

        // Verify the order is an imaging order
        if (! $labOrder->labService || ! $labOrder->labService->is_imaging) {
            return false;
        }

        return true;
    }
}
