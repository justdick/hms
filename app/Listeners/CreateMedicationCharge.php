<?php

namespace App\Listeners;

use App\Events\PrescriptionCreated;
use App\Services\BillingService;

class CreateMedicationCharge
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function handle(PrescriptionCreated $event): void
    {
        $prescription = $event->prescription;

        if ($prescription->drug && $prescription->drug->selling_price > 0) {
            $this->billingService->createMedicationCharge(
                checkin: $prescription->consultation->patientCheckin,
                drugCode: $prescription->drug->code ?? 'DRUG_'.$prescription->drug->id,
                amount: $prescription->drug->selling_price * $prescription->quantity,
                drugName: $prescription->drug->name,
                quantity: $prescription->quantity
            );
        }
    }
}
