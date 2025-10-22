<?php

namespace App\Listeners;

use App\Events\LabTestOrdered;
use App\Services\BillingService;

class CreateLabTestCharge
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function handle(LabTestOrdered $event): void
    {
        $labOrder = $event->labOrder;

        // Get the patient check-in based on whether it's OPD or IPD
        $checkin = $this->getPatientCheckin($labOrder);

        // If no check-in is found, skip charge creation
        if (! $checkin) {
            return;
        }

        $this->billingService->createLabTestCharge(
            checkin: $checkin,
            testCode: $labOrder->labService->code,
            amount: $labOrder->labService->price,
            testName: $labOrder->labService->name
        );
    }

    private function getPatientCheckin($labOrder): ?\App\Models\PatientCheckin
    {
        // For consultation-based lab orders (OPD)
        if ($labOrder->orderable_type === \App\Models\Consultation::class) {
            $labOrder->loadMissing('orderable.patientCheckin');

            return $labOrder->orderable?->patientCheckin;
        }

        // For ward round-based lab orders (IPD)
        if ($labOrder->orderable_type === \App\Models\WardRound::class) {
            $labOrder->loadMissing('orderable.patientAdmission.consultation.patientCheckin');

            return $labOrder->orderable?->patientAdmission?->consultation?->patientCheckin;
        }

        // Fallback to old consultation relationship for backward compatibility
        $labOrder->loadMissing('consultation.patientCheckin');

        return $labOrder->consultation?->patientCheckin;
    }
}
