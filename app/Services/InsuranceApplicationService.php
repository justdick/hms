<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service for applying insurance to checkins and charges.
 * Handles both initial application and retroactive application when
 * insurance is added mid-checkin (e.g., emergency patient scenario).
 */
class InsuranceApplicationService
{
    public function __construct(
        protected InsuranceService $insuranceService
    ) {}

    /**
     * Apply insurance to an active checkin that was created without insurance.
     * This handles the emergency scenario where a patient arrives without
     * their insurance card, and it's added later during the same visit.
     *
     * @return array{success: bool, message: string, claim_id?: int, charges_updated?: int}
     */
    public function applyInsuranceToActiveCheckin(
        PatientCheckin $checkin,
        PatientInsurance $patientInsurance,
        string $claimCheckCode
    ): array {
        // Validate checkin doesn't already have insurance
        if ($checkin->claim_check_code) {
            return [
                'success' => false,
                'message' => 'This check-in already has insurance applied.',
            ];
        }

        // Validate checkin is still active (not completed or cancelled)
        if (in_array($checkin->status, ['completed', 'cancelled'])) {
            return [
                'success' => false,
                'message' => 'Cannot apply insurance to a completed or cancelled check-in.',
            ];
        }

        return DB::transaction(function () use ($checkin, $patientInsurance, $claimCheckCode) {
            $patient = $checkin->patient;

            // Create the insurance claim
            $claim = InsuranceClaim::create([
                'claim_check_code' => $claimCheckCode,
                'folder_id' => $patient->patient_number,
                'patient_id' => $patient->id,
                'patient_insurance_id' => $patientInsurance->id,
                'patient_checkin_id' => $checkin->id,
                'patient_surname' => $patient->last_name,
                'patient_other_names' => $patient->first_name,
                'patient_dob' => $patient->date_of_birth,
                'patient_gender' => $patient->gender,
                'membership_id' => $patientInsurance->membership_id,
                'date_of_attendance' => $checkin->checked_in_at,
                'type_of_service' => 'outpatient',
                'type_of_attendance' => 'routine',
                'status' => 'pending_vetting',
                'total_claim_amount' => 0,
                'approved_amount' => 0,
                'patient_copay_amount' => 0,
                'insurance_covered_amount' => 0,
            ]);

            // Update checkin with claim check code
            $checkin->update(['claim_check_code' => $claimCheckCode]);

            // Get all pending charges for this checkin and apply insurance
            $charges = Charge::where('patient_checkin_id', $checkin->id)
                ->where('status', 'pending')
                ->where('is_insurance_claim', false)
                ->get();

            $chargesUpdated = 0;

            foreach ($charges as $charge) {
                $this->applyInsuranceToCharge($charge, $claim, $patientInsurance);
                $chargesUpdated++;
            }

            return [
                'success' => true,
                'message' => "Insurance applied successfully. {$chargesUpdated} charge(s) updated with coverage.",
                'claim_id' => $claim->id,
                'charges_updated' => $chargesUpdated,
            ];
        });
    }

    /**
     * Apply insurance coverage to a single charge.
     */
    protected function applyInsuranceToCharge(
        Charge $charge,
        InsuranceClaim $claim,
        PatientInsurance $patientInsurance
    ): void {
        // Map charge service_type to insurance item_type
        $itemType = $this->mapServiceTypeToItemType($charge->service_type);
        if (! $itemType) {
            return; // Skip charges that don't map to insurance categories
        }

        // Calculate coverage
        $coverage = $this->insuranceService->calculateCoverage(
            $patientInsurance,
            $itemType,
            $charge->service_code ?? 'GENERAL',
            (float) $charge->amount,
            1
        );

        // Update charge with insurance information
        $charge->update([
            'insurance_claim_id' => $claim->id,
            'is_insurance_claim' => true,
            'insurance_tariff_amount' => $coverage['insurance_tariff'],
            'insurance_covered_amount' => $coverage['insurance_pays'],
            'patient_copay_amount' => $coverage['patient_pays'],
            'amount' => $coverage['is_covered'] ? $coverage['insurance_tariff'] : $charge->amount,
        ]);

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

        // Update charge with claim item reference
        $charge->updateQuietly(['insurance_claim_item_id' => $claimItem->id]);

        // Update claim totals
        $claim->increment('total_claim_amount', $coverage['subtotal']);
        $claim->increment('insurance_covered_amount', $coverage['insurance_pays']);
        $claim->increment('patient_copay_amount', $coverage['patient_pays']);
    }

    /**
     * Check if a patient has any active checkins without insurance.
     */
    public function getActiveCheckinWithoutInsurance(Patient $patient): ?PatientCheckin
    {
        return PatientCheckin::where('patient_id', $patient->id)
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
            ->whereNull('claim_check_code')
            ->first();
    }

    /**
     * Generate a unique claim check code.
     */
    public function generateClaimCheckCode(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        // Ensure uniqueness
        $code = "CC-{$date}-{$random}";
        $attempts = 0;

        while (InsuranceClaim::where('claim_check_code', $code)->exists() && $attempts < 10) {
            $random = strtoupper(Str::random(4));
            $code = "CC-{$date}-{$random}";
            $attempts++;
        }

        return $code;
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
}
