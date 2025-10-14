<?php

namespace App\Listeners;

use App\Events\PatientCheckedIn;
use App\Services\BillingService;

class CreateConsultationCharge
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function handle(PatientCheckedIn $event): void
    {
        $this->billingService->createConsultationCharge($event->checkin);
    }
}
