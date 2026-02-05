<?php

namespace App\Services;

use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;

class PharmacyBillingService
{
    public function __construct(
        protected InsuranceCoverageService $coverageService,
        protected InsuranceClaimService $claimService
    ) {
    }

    /**
     * Create a charge for a prescription when it's created by the doctor.
     */
    public function createChargeForPrescription(Prescription $prescription): Charge
    {
        $drug = $prescription->drug;
        $quantity = $prescription->quantity;

        // Calculate amount: unit price × quantity
        $amount = $drug->unit_price * $quantity;

        $user = Auth::user();

        // Get patient_checkin_id based on prescription type
        $patientCheckinId = $this->getPatientCheckinId($prescription);

        // Calculate insurance coverage if patient has active insurance
        $coverageData = $this->calculateInsuranceCoverage(
            $patientCheckinId,
            $drug->drug_code,
            $drug->id,
            $amount,
            $quantity
        );

        $charge = Charge::create([
            'patient_checkin_id' => $patientCheckinId,
            'prescription_id' => $prescription->id,
            'service_type' => 'pharmacy',
            'service_code' => $drug->drug_code,
            'description' => "{$drug->name} ({$quantity} {$drug->form})",
            'amount' => $amount,
            'insurance_tariff_amount' => $coverageData['insurance_tariff'] ?? $amount,
            'charge_type' => 'medication',
            'status' => 'pending',
            'is_insurance_claim' => $coverageData['is_insurance_claim'],
            'insurance_covered_amount' => $coverageData['insurance_covered_amount'],
            'patient_copay_amount' => $coverageData['patient_copay_amount'],
            'charged_at' => now(),
            'created_by_type' => $user ? get_class($user) : null,
            'created_by_id' => $user?->id,
            'notes' => "Auto-generated from prescription #{$prescription->id}",
        ]);

        // Auto-link charge to insurance claim if patient has one for this check-in
        $this->linkChargeToInsuranceClaim($charge, $patientCheckinId);

        return $charge;
    }

    /**
     * Calculate insurance coverage for a drug charge.
     */
    protected function calculateInsuranceCoverage(
        ?int $patientCheckinId,
        string $drugCode,
        int $drugId,
        float $amount,
        int $quantity
    ): array {
        // Default: no insurance coverage
        $defaultResult = [
            'is_insurance_claim' => false,
            'insurance_covered_amount' => 0.00,
            'patient_copay_amount' => $amount,
            'insurance_tariff' => $amount,
        ];

        if (!$patientCheckinId) {
            return $defaultResult;
        }

        // Get patient's active insurance from checkin
        $checkin = PatientCheckin::with('patient.activeInsurance.plan')->find($patientCheckinId);

        if (!$checkin || !$checkin->patient || !$checkin->patient->activeInsurance) {
            return $defaultResult;
        }

        $insurancePlanId = $checkin->patient->activeInsurance->insurance_plan_id;

        // Calculate coverage using the coverage service
        $coverage = $this->coverageService->calculateCoverage(
            insurancePlanId: $insurancePlanId,
            category: 'drug',
            itemCode: $drugCode,
            amount: $amount / $quantity, // Unit price
            quantity: $quantity,
            date: now(),
            itemId: $drugId,
            itemType: 'drug'
        );

        return [
            'is_insurance_claim' => true,
            'insurance_covered_amount' => $coverage['insurance_pays'],
            'patient_copay_amount' => $coverage['patient_pays'],
            'insurance_tariff' => $coverage['insurance_tariff'] * $quantity,
        ];
    }

    /**
     * Get patient_checkin_id from prescription, handling both consultation and ward round prescriptions.
     */
    protected function getPatientCheckinId(Prescription $prescription): ?int
    {
        // If prescription has a direct consultation relationship
        if ($prescription->consultation_id && $prescription->consultation) {
            return $prescription->consultation->patient_checkin_id;
        }

        // If prescription belongs to a ward round (polymorphic relationship)
        if ($prescription->prescribable_type === 'App\Models\WardRound' && $prescription->prescribable) {
            return $prescription->prescribable->patientAdmission?->consultation?->patient_checkin_id;
        }

        return null;
    }

    /**
     * Update charge when prescription quantity is adjusted during pharmacy review.
     * Returns null if no charge exists (e.g., for unpriced drugs).
     */
    public function updateChargeForReview(Prescription $prescription, int $newQuantity, ?string $reason = null): ?Charge
    {
        $charge = $prescription->charge;

        // For unpriced drugs, no charge exists - just return null
        if (!$charge) {
            return null;
        }

        $drug = $prescription->drug;
        $newAmount = $drug->unit_price * $newQuantity;

        $charge->update([
            'amount' => $newAmount,
            'description' => "{$drug->name} ({$newQuantity} {$drug->dosage_form})",
            'notes' => $reason ? "Adjusted: {$reason}" : 'Quantity adjusted during pharmacy review',
        ]);

        return $charge->fresh();
    }

    /**
     * Void charge when prescription is marked as external.
     * Returns null if no charge exists (e.g., for unpriced drugs).
     */
    public function voidChargeForExternal(Prescription $prescription, string $reason): ?Charge
    {
        $charge = $prescription->charge;

        // For unpriced drugs, no charge exists - just return null
        if (!$charge) {
            return null;
        }

        $charge->update([
            'status' => 'cancelled',
            'amount' => 0,
            'notes' => "External prescription: {$reason}",
        ]);

        return $charge->fresh();
    }

    /**
     * Check if dispensing is allowed based on payment status and billing configuration.
     */
    public function canDispense(Prescription $prescription): bool
    {
        $requirePayment = BillingConfiguration::getValue('pharmacy.require_payment_before_dispensing', true);

        // If payment is not required by configuration, allow dispensing
        if (!$requirePayment) {
            return true;
        }

        // Check if it's an unpriced drug (no charge created because price is 0)
        $drug = $prescription->drug;
        if ($drug && ($drug->unit_price === null || $drug->unit_price == 0)) {
            // Unpriced drugs can be dispensed without payment
            return true;
        }

        $charge = $prescription->charge;

        // No charge and not an unpriced drug - shouldn't happen, but deny dispensing
        if (!$charge) {
            return false;
        }

        // Check if charge is paid or waived
        return in_array($charge->status, ['paid', 'waived']);
    }

    /**
     * Check if payment can be overridden (for emergency cases).
     */
    public function canOverridePayment(): bool
    {
        // Check if user has permission to override payment requirement
        return Auth::user()->can('dispensing.override-payment');
    }

    /**
     * Calculate total charges for multiple prescriptions.
     */
    public function calculateTotalCharges(array $prescriptionIds): array
    {
        $charges = Charge::whereIn('prescription_id', $prescriptionIds)
            ->where('status', '!=', 'cancelled')
            ->get();

        $total = $charges->sum('amount');
        $paid = $charges->where('status', 'paid')->sum('amount');
        $pending = $charges->where('status', 'pending')->sum('amount');

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'charges' => $charges,
        ];
    }

    /**
     * Get payment status summary for a patient's prescriptions.
     */
    public function getPaymentStatusSummary(int $patientCheckinId): array
    {
        $charges = Charge::where('patient_checkin_id', $patientCheckinId)
            ->where('service_type', 'pharmacy')
            ->whereNotNull('prescription_id')
            ->get();

        $totalAmount = $charges->sum('amount');
        $paidAmount = $charges->whereIn('status', ['paid', 'waived'])->sum('amount');
        $pendingAmount = $charges->where('status', 'pending')->sum('amount');

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'all_paid' => $pendingAmount == 0,
            'has_pending' => $pendingAmount > 0,
            'charges_count' => $charges->count(),
            'paid_charges_count' => $charges->whereIn('status', ['paid', 'waived'])->count(),
            'pending_charges_count' => $charges->where('status', 'pending')->count(),
        ];
    }

    /**
     * Create a charge for a minor procedure supply when it's dispensed.
     */
    public function createSupplyCharge(\App\Models\MinorProcedureSupply $supply): Charge
    {
        $drug = $supply->drug;
        $quantity = $supply->quantity;

        // Calculate amount: unit price × quantity
        $amount = $drug->unit_price * $quantity;

        $user = Auth::user();

        // Get patient_checkin_id from the minor procedure
        $patientCheckinId = $supply->minorProcedure->patient_checkin_id;

        return Charge::create([
            'patient_checkin_id' => $patientCheckinId,
            'service_type' => 'pharmacy',
            'service_code' => $drug->drug_code,
            'description' => "Minor Procedure Supply: {$drug->name} ({$quantity} {$drug->form})",
            'amount' => $amount,
            'charge_type' => 'supply',
            'status' => 'pending',
            'charged_at' => now(),
            'created_by_type' => $user ? get_class($user) : null,
            'created_by_id' => $user?->id,
            'notes' => "Auto-generated from minor procedure supply #{$supply->id}",
            'metadata' => [
                'minor_procedure_supply_id' => $supply->id,
                'minor_procedure_id' => $supply->minor_procedure_id,
                'quantity' => $quantity,
            ],
        ]);
    }

    /**
     * Link a charge to its insurance claim if one exists for the check-in.
     * This ensures prescriptions and other charges are added to claims automatically.
     */
    protected function linkChargeToInsuranceClaim(Charge $charge, ?int $patientCheckinId): void
    {
        if (!$patientCheckinId) {
            return;
        }

        // Find the insurance claim for this check-in
        $claim = \App\Models\InsuranceClaim::where('patient_checkin_id', $patientCheckinId)->first();

        if (!$claim) {
            return;
        }

        // Skip if charge is already linked to this claim
        $alreadyLinked = \App\Models\InsuranceClaimItem::where('insurance_claim_id', $claim->id)
            ->where('charge_id', $charge->id)
            ->exists();

        if ($alreadyLinked) {
            return;
        }

        // Link the charge to the claim
        $this->claimService->addChargesToClaim($claim, [$charge->id]);
    }
}
