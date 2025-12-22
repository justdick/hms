<?php

namespace App\Observers;

use App\Models\LabOrder;
use App\Models\LabService;

class LabOrderObserver
{
    /**
     * Handle the LabOrder "creating" event.
     * Auto-set unpriced lab services to external referral.
     */
    public function creating(LabOrder $labOrder): void
    {
        // Check if lab service is unpriced (null or zero price)
        if ($labOrder->lab_service_id) {
            $labService = $labOrder->labService ?? LabService::find($labOrder->lab_service_id);

            if ($labService && ($labService->price === null || (float) $labService->price === 0.0)) {
                $labOrder->is_unpriced = true;
                $labOrder->status = 'external_referral';
            } else {
                // Explicitly set to false for priced lab services
                $labOrder->is_unpriced = false;
            }
        } else {
            // No lab_service_id means it's not a valid order
            $labOrder->is_unpriced = false;
        }
    }

    /**
     * Handle the LabOrder "created" event.
     */
    public function created(LabOrder $labOrder): void
    {
        // Skip charge creation for unpriced lab orders (they are marked external referral)
        // Charge creation is handled by the LabTestOrdered event listener
    }

    /**
     * Handle the LabOrder "updated" event.
     */
    public function updated(LabOrder $labOrder): void
    {
        //
    }

    /**
     * Handle the LabOrder "deleted" event.
     */
    public function deleted(LabOrder $labOrder): void
    {
        //
    }

    /**
     * Handle the LabOrder "restored" event.
     */
    public function restored(LabOrder $labOrder): void
    {
        //
    }

    /**
     * Handle the LabOrder "force deleted" event.
     */
    public function forceDeleted(LabOrder $labOrder): void
    {
        //
    }
}
