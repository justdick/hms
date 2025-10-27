<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\MedicationScheduleService;
use App\Services\PharmacyBillingService;

class PrescriptionObserver
{
    public function __construct(
        protected PharmacyBillingService $billingService,
        protected MedicationScheduleService $scheduleService
    ) {}

    /**
     * Handle the Prescription "created" event.
     */
    public function created(Prescription $prescription): void
    {
        // Auto-create charge when prescription with drug and quantity is created
        // Skip if no quantity (ward rounds don't set quantity initially, it's set during pharmacy review)
        if ($prescription->drug_id && $prescription->quantity && $prescription->isPrescribed()) {
            $this->billingService->createChargeForPrescription($prescription);
        }

        // Generate medication administration schedule for admitted patients
        if ($prescription->frequency && $prescription->duration) {
            $this->scheduleService->generateSchedule($prescription);
        }
    }

    /**
     * Handle the Prescription "updated" event.
     */
    public function updated(Prescription $prescription): void
    {
        // Regenerate schedule if frequency or duration changed
        if ($prescription->isDirty(['frequency', 'duration', 'dose_quantity'])) {
            if ($prescription->frequency && $prescription->duration) {
                $this->scheduleService->regenerateSchedule($prescription);
            }
        }
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
