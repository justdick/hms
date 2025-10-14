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

        $this->billingService->createLabTestCharge(
            checkin: $labOrder->consultation->patientCheckin,
            testCode: $labOrder->labService->code,
            amount: $labOrder->labService->price,
            testName: $labOrder->labService->name
        );
    }
}
