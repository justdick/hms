<?php

namespace App\Observers;

use App\Models\BillingService;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\User;
use App\Notifications\NewItemAddedNotification;

class DrugObserver
{
    /**
     * Handle the Drug "created" event.
     */
    public function created(Drug $drug): void
    {
        BillingService::create([
            'service_name' => $drug->name.($drug->brand_name ? " ({$drug->brand_name})" : ''),
            'service_code' => 'DRUG_'.$drug->drug_code,
            'service_type' => 'medication',
            'base_price' => $drug->unit_price,
            'is_active' => $drug->is_active,
        ]);

        $this->notifyInsuranceAdmins($drug);
    }

    /**
     * Notify insurance administrators about the new drug.
     */
    protected function notifyInsuranceAdmins(Drug $drug): void
    {
        // Find all insurance plans with default coverage for drugs
        $plansWithDrugCoverage = InsuranceCoverageRule::whereNull('item_code')
            ->where('coverage_category', 'drug')
            ->where('is_active', true)
            ->with('plan')
            ->get();

        foreach ($plansWithDrugCoverage as $rule) {
            // Check if plan requires explicit approval
            if ($rule->plan->require_explicit_approval_for_new_items) {
                // Don't notify, item won't be covered until reviewed
                continue;
            }

            // Create notification for insurance admins
            $admins = User::role('insurance_admin')->get();

            foreach ($admins as $admin) {
                $admin->notify(new NewItemAddedNotification(
                    item: $drug,
                    category: 'drug',
                    plan: $rule->plan,
                    defaultCoverage: $rule->coverage_value
                ));
            }
        }
    }

    /**
     * Handle the Drug "updated" event.
     */
    public function updated(Drug $drug): void
    {
        $billingService = BillingService::where('service_code', 'DRUG_'.$drug->drug_code)->first();

        if ($billingService) {
            $billingService->update([
                'service_name' => $drug->name.($drug->brand_name ? " ({$drug->brand_name})" : ''),
                'base_price' => $drug->unit_price,
                'is_active' => $drug->is_active,
            ]);
        }
    }

    /**
     * Handle the Drug "deleted" event.
     */
    public function deleted(Drug $drug): void
    {
        BillingService::where('service_code', 'DRUG_'.$drug->drug_code)->delete();
    }
}
