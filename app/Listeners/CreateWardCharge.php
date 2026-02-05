<?php

namespace App\Listeners;

use App\Events\BedAssigned;
use App\Events\PatientAdmitted;
use App\Models\InsuranceClaim;
use App\Services\BillingService;

class CreateWardCharge
{
    public function __construct(
        private BillingService $billingService
    ) {
    }

    public function handleAdmission(PatientAdmitted $event): void
    {
        $this->billingService->createWardCharges(
            checkin: $event->checkin,
            wardType: $event->wardType,
            bedNumber: $event->bedNumber
        );

        // Update the claim to 'inpatient' when patient is admitted
        // This ensures services done before admission are correctly classified as IPD
        InsuranceClaim::where('patient_checkin_id', $event->checkin->id)
            ->where('type_of_service', '!=', 'inpatient')
            ->update(['type_of_service' => 'inpatient']);
    }

    public function handleBedAssignment(BedAssigned $event): void
    {
        $this->billingService->createWardCharges(
            checkin: $event->checkin,
            wardType: $event->wardType,
            bedNumber: $event->bedNumber
        );
    }
}
