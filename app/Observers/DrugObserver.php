<?php

namespace App\Observers;

use App\Models\BillingService;
use App\Models\Drug;

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
