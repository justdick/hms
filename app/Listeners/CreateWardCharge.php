<?php

namespace App\Listeners;

use App\Events\BedAssigned;
use App\Events\PatientAdmitted;
use App\Services\BillingService;

class CreateWardCharge
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function handleAdmission(PatientAdmitted $event): void
    {
        $this->billingService->createWardCharges(
            checkin: $event->checkin,
            wardType: $event->wardType,
            bedNumber: $event->bedNumber
        );
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
