<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\PharmacyBillingService;

class PrescriptionObserver
{
    public function __construct(
        protected PharmacyBillingService $billingService
    ) {}

    /**
     * Handle the Prescription "created" event.
     */
    public function created(Prescription $prescription): void
    {
        // Auto-create charge when prescription with drug is created
        if ($prescription->drug_id && $prescription->isPrescribed()) {
            $this->billingService->createChargeForPrescription($prescription);
        }
    }

    /**
     * Handle the Prescription "updated" event.
     */
    public function updated(Prescription $prescription): void
    {
        // Track status changes or other updates if needed
    }

    /**
     * Handle the Prescription "deleted" event.
     */
    public function deleted(Prescription $prescription): void
    {
        // Void associated charge if prescription is deleted
        if ($prescription->charge && $prescription->charge->status === 'pending') {
            $prescription->charge->update([
                'status' => 'cancelled',
                'notes' => 'Prescription deleted',
            ]);
        }
    }

    /**
     * Handle the Prescription "restored" event.
     */
    public function restored(Prescription $prescription): void
    {
        //
    }

    /**
     * Handle the Prescription "force deleted" event.
     */
    public function forceDeleted(Prescription $prescription): void
    {
        //
    }
}
