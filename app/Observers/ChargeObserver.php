<?php

namespace App\Observers;

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Services\InsuranceService;
use App\Services\PatientAccountService;

class ChargeObserver
{
    public function __construct(
        protected InsuranceService $insuranceService,
        protected PatientAccountService $patientAccountService
    ) {}

    /**
     * Handle the Charge "creating" event.
     * This runs BEFORE the charge is saved, allowing us to calculate insurance coverage.
     */
    public function creating(Charge $charge): void
    {
        // Skip if already marked as insurance claim
        if ($charge->is_insurance_claim) {
            return;
        }

        // Check if this check-in has an active insurance claim
        if (! $charge->patientCheckin?->claim_check_code) {
            return;
        }

        $checkin = $charge->patientCheckin;
        $claim = InsuranceClaim::where('claim_check_code', $checkin->claim_check_code)
            ->where('status', '!=', 'cancelled')
            ->first();

        if (! $claim) {
            return;
        }

        // Get patient insurance
        $patientInsurance = $claim->patientInsurance;
        if (! $patientInsurance) {
            return;
        }

        // Map charge service_type to insurance item_type
        $itemType = $this->mapServiceTypeToItemType($charge->service_type);
        if (! $itemType) {
            return;
        }

        // Get item ID for NHIS coverage lookup
        // For consultations, use department_id; for other types, extract from metadata or related models
        $itemId = $this->getItemIdForCharge($charge, $checkin);

        // Calculate coverage
        $coverage = $this->insuranceService->calculateCoverage(
            $patientInsurance,
            $itemType,
            $charge->service_code ?? 'GENERAL',
            (float) $charge->amount,
            1,
            null,
            $itemId
        );

        // Update charge with insurance information
        $charge->insurance_claim_id = $claim->id;
        $charge->is_insurance_claim = true;
        $charge->insurance_tariff_amount = $coverage['insurance_tariff'];
        $charge->insurance_covered_amount = $coverage['insurance_pays'];
        $charge->patient_copay_amount = $coverage['patient_pays'];

        // If fully covered, update the amount to match the insurance tariff
        if ($coverage['is_covered'] && $coverage['insurance_tariff'] != $charge->amount) {
            $charge->amount = $coverage['insurance_tariff'];
        }
    }

    /**
     * Handle the Charge "created" event.
     * This runs AFTER the charge is saved, allowing us to create the claim item.
     */
    public function created(Charge $charge): void
    {
        // Handle insurance claim item creation
        $this->handleInsuranceClaimItem($charge);

        // Auto-apply patient deposits to charge
        $this->autoApplyDeposit($charge);
    }

    /**
     * Handle insurance claim item creation for a charge.
     */
    protected function handleInsuranceClaimItem(Charge $charge): void
    {
        // Skip if not an insurance claim
        if (! $charge->is_insurance_claim || ! $charge->insurance_claim_id) {
            return;
        }

        $claim = InsuranceClaim::find($charge->insurance_claim_id);
        if (! $claim) {
            return;
        }

        // Get patient insurance
        $patientInsurance = $claim->patientInsurance;
        if (! $patientInsurance) {
            return;
        }

        // Map charge service_type to insurance item_type
        $itemType = $this->mapServiceTypeToItemType($charge->service_type);
        if (! $itemType) {
            return;
        }

        // Get item ID for NHIS coverage lookup
        $checkin = $charge->patientCheckin;
        $itemId = $checkin ? $this->getItemIdForCharge($charge, $checkin) : null;

        // Calculate coverage again (in case it wasn't done in creating)
        $coverage = $this->insuranceService->calculateCoverage(
            $patientInsurance,
            $itemType,
            $charge->service_code ?? 'GENERAL',
            (float) $charge->amount,
            1,
            null,
            $itemId
        );

        // Create insurance claim item
        $claimItem = InsuranceClaimItem::create([
            'insurance_claim_id' => $claim->id,
            'charge_id' => $charge->id,
            'item_date' => now()->toDateString(),
            'item_type' => $itemType,
            'code' => $charge->service_code ?? 'GENERAL',
            'description' => $charge->description,
            'quantity' => 1,
            'unit_tariff' => $coverage['insurance_tariff'],
            'subtotal' => $coverage['subtotal'],
            'is_covered' => $coverage['is_covered'],
            'coverage_percentage' => $coverage['coverage_percentage'],
            'insurance_pays' => $coverage['insurance_pays'],
            'patient_pays' => $coverage['patient_pays'],
            'is_approved' => false,
        ]);

        // Update charge with claim item reference (quietly to avoid triggering updated event)
        $charge->updateQuietly(['insurance_claim_item_id' => $claimItem->id]);

        // Update claim totals
        $claim->increment('total_claim_amount', $coverage['subtotal']);
        $claim->increment('insurance_covered_amount', $coverage['insurance_pays']);
        $claim->increment('patient_copay_amount', $coverage['patient_pays']);
    }

    /**
     * Auto-apply patient account balance to a newly created charge.
     */
    protected function autoApplyDeposit(Charge $charge): void
    {
        // Only apply to pending charges
        if ($charge->status !== 'pending') {
            return;
        }

        // Apply from patient account (handles both prepaid balance and credit limit)
        $this->patientAccountService->applyToCharge($charge);
    }

    /**
     * Handle the Charge "updated" event.
     */
    public function updated(Charge $charge): void
    {
        // If the charge amount was updated and it's an insurance claim, recalculate
        if ($charge->wasChanged('amount') && $charge->is_insurance_claim && $charge->insurance_claim_item_id) {
            $claimItem = InsuranceClaimItem::find($charge->insurance_claim_item_id);
            if ($claimItem && $claimItem->claim) {
                $claim = $claimItem->claim;
                $patientInsurance = $claim->patientInsurance;

                if ($patientInsurance) {
                    $itemType = $this->mapServiceTypeToItemType($charge->service_type);
                    if ($itemType) {
                        $coverage = $this->insuranceService->calculateCoverage(
                            $patientInsurance,
                            $itemType,
                            $charge->service_code ?? 'GENERAL',
                            (float) $charge->amount,
                            1
                        );

                        // Update the claim item
                        $oldInsurancePays = $claimItem->insurance_pays;
                        $oldPatientPays = $claimItem->patient_pays;
                        $oldSubtotal = $claimItem->subtotal;

                        $claimItem->update([
                            'unit_tariff' => $coverage['insurance_tariff'],
                            'subtotal' => $coverage['subtotal'],
                            'coverage_percentage' => $coverage['coverage_percentage'],
                            'insurance_pays' => $coverage['insurance_pays'],
                            'patient_pays' => $coverage['patient_pays'],
                        ]);

                        // Update charge insurance amounts (quietly to avoid infinite recursion)
                        $charge->updateQuietly([
                            'insurance_tariff_amount' => $coverage['insurance_tariff'],
                            'insurance_covered_amount' => $coverage['insurance_pays'],
                            'patient_copay_amount' => $coverage['patient_pays'],
                        ]);

                        // Update claim totals (adjust for the difference)
                        $claim->decrement('total_claim_amount', $oldSubtotal);
                        $claim->increment('total_claim_amount', $coverage['subtotal']);

                        $claim->decrement('insurance_covered_amount', $oldInsurancePays);
                        $claim->increment('insurance_covered_amount', $coverage['insurance_pays']);

                        $claim->decrement('patient_copay_amount', $oldPatientPays);
                        $claim->increment('patient_copay_amount', $coverage['patient_pays']);
                    }
                }
            }
        }
    }

    /**
     * Handle the Charge "deleted" event.
     */
    public function deleted(Charge $charge): void
    {
        // If this is an insurance claim, delete the associated claim item
        if ($charge->is_insurance_claim && $charge->insurance_claim_item_id) {
            $claimItem = InsuranceClaimItem::find($charge->insurance_claim_item_id);
            if ($claimItem) {
                $claim = $claimItem->claim;

                // Update claim totals before deleting
                if ($claim) {
                    $claim->decrement('total_claim_amount', $claimItem->subtotal);
                    $claim->decrement('insurance_covered_amount', $claimItem->insurance_pays);
                    $claim->decrement('patient_copay_amount', $claimItem->patient_pays);
                }

                $claimItem->delete();
            }
        }
    }

    /**
     * Map charge service_type to insurance item_type.
     */
    protected function mapServiceTypeToItemType(string $serviceType): ?string
    {
        return match ($serviceType) {
            'consultation' => 'consultation',
            'pharmacy', 'drug' => 'drug',
            'lab', 'laboratory' => 'lab',
            'procedure' => 'procedure',
            'ward', 'admission' => 'ward',
            'nursing' => 'nursing',
            default => null,
        };
    }

    /**
     * Get the item ID for a charge based on its service type.
     * This is used for NHIS coverage lookup which requires the actual item ID.
     */
    protected function getItemIdForCharge(Charge $charge, \App\Models\PatientCheckin $checkin): ?int
    {
        return match ($charge->service_type) {
            // For consultations, the item ID is the department ID
            'consultation' => $checkin->department_id,
            // For other types, we would need to look up the item from the service_code
            // This can be extended as needed for drugs, labs, procedures, etc.
            default => null,
        };
    }
}
