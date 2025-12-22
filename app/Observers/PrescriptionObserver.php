<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\PharmacyBillingService;

class PrescriptionObserver
{
    public function __construct(
        protected PharmacyBillingService $billingService,
    ) {}

    /**
     * Handle the Prescription "creating" event.
     * Flag unpriced drugs but keep status as 'prescribed' so they appear in dispensing queue.
     * Pharmacist will see the flag and can choose to mark as external during review.
     */
    public function creating(Prescription $prescription): void
    {
        // Check if drug is unpriced (null or zero price)
        if ($prescription->drug_id) {
            $drug = $prescription->drug ?? \App\Models\Drug::find($prescription->drug_id);

            if ($drug && ($drug->unit_price === null || (float) $drug->unit_price === 0.0)) {
                // Flag as unpriced but keep status as 'prescribed'
                // Pharmacist will see this flag during review and can mark as external
                $prescription->is_unpriced = true;
            } else {
                // Explicitly set to false for priced drugs
                $prescription->is_unpriced = false;
            }
        } else {
            // No drug_id means it's a custom medication, not unpriced
            $prescription->is_unpriced = false;
        }
    }

    /**
     * Handle the Prescription "created" event.
     */
    public function created(Prescription $prescription): void
    {
        // Skip charge creation for unpriced drugs (they are marked external)
        if ($prescription->is_unpriced) {
            return;
        }

        // Auto-create charge when prescription with drug and quantity is created
        // Skip if no quantity (ward rounds don't set quantity initially, it's set during pharmacy review)
        if ($prescription->drug_id && $prescription->quantity && $prescription->isPrescribed()) {
            $this->billingService->createChargeForPrescription($prescription);
        }

        // Note: Medication schedules are NOT auto-generated here.
        // Ward staff must configure medication schedules via "Medication History" tab
        // to ensure appropriate timing for ward rounds.
    }

    /**
     * Handle the Prescription "updated" event.
     */
    public function updated(Prescription $prescription): void
    {
        // Note: Schedule regeneration is handled manually by ward staff
        // via the "Reconfigure Schedule" feature in "Medication History" tab.
        // This ensures ward staff maintain control over medication timing.
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

        // Delete all scheduled medication administrations
        $prescription->medicationAdministrations()
            ->where('status', 'scheduled')
            ->delete();
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
