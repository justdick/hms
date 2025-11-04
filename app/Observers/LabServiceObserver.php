<?php

namespace App\Observers;

use App\Models\BillingService;
use App\Models\InsuranceCoverageRule;
use App\Models\LabService;
use App\Models\User;
use App\Notifications\NewItemAddedNotification;

class LabServiceObserver
{
    /**
     * Handle the LabService "created" event.
     */
    public function created(LabService $labService): void
    {
        BillingService::create([
            'service_name' => $labService->name,
            'service_code' => 'LAB_'.$labService->code,
            'service_type' => 'lab_test',
            'base_price' => $labService->price,
            'is_active' => $labService->is_active,
        ]);

        $this->notifyInsuranceAdmins($labService);
    }

    /**
     * Handle the LabService "updated" event.
     */
    public function updated(LabService $labService): void
    {
        $billingService = BillingService::where('service_code', 'LAB_'.$labService->code)->first();

        if ($billingService) {
            $billingService->update([
                'service_name' => $labService->name,
                'base_price' => $labService->price,
                'is_active' => $labService->is_active,
            ]);
        }
    }

    /**
     * Handle the LabService "deleted" event.
     */
    public function deleted(LabService $labService): void
    {
        BillingService::where('service_code', 'LAB_'.$labService->code)->delete();
    }

    /**
     * Notify insurance administrators about the new lab service.
     */
    protected function notifyInsuranceAdmins(LabService $labService): void
    {
        // Find all insurance plans with default coverage for labs
        $plansWithLabCoverage = InsuranceCoverageRule::whereNull('item_code')
            ->where('coverage_category', 'lab')
            ->where('is_active', true)
            ->with('plan')
            ->get();

        foreach ($plansWithLabCoverage as $rule) {
            // Check if plan requires explicit approval
            if ($rule->plan->require_explicit_approval_for_new_items) {
                // Don't notify, item won't be covered until reviewed
                continue;
            }

            // Create notification for insurance admins
            $admins = User::role('insurance_admin')->get();

            foreach ($admins as $admin) {
                $admin->notify(new NewItemAddedNotification(
                    item: $labService,
                    category: 'lab',
                    plan: $rule->plan,
                    defaultCoverage: $rule->coverage_value
                ));
            }
        }
    }
}
