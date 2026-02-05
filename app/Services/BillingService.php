<?php

namespace App\Services;

use App\Models\BillingConfiguration;
use App\Models\BillingOverride;
use App\Models\Charge;
use App\Models\DepartmentBilling;
use App\Models\PatientCheckin;
use App\Models\ServiceAccessOverride;
use App\Models\ServiceChargeRule;
use App\Models\WardBillingTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BillingService
{
    public function createConsultationCharge(PatientCheckin $checkin): ?Charge
    {
        if (!BillingConfiguration::getValue('auto_billing_enabled', true)) {
            return null;
        }

        // Check if NHIS patient should skip consultation fee (one-time charge rule)
        if ($this->shouldSkipNhisConsultationFee($checkin)) {
            return null;
        }

        $departmentBilling = DepartmentBilling::getForDepartment($checkin->department_id);

        if (!$departmentBilling) {
            return null;
        }

        $consultationCharge = $this->createCharge(
            checkin: $checkin,
            serviceType: 'consultation',
            serviceCode: $departmentBilling->department_code,
            description: "Consultation fee for {$departmentBilling->department_name}",
            amount: $departmentBilling->consultation_fee,
            chargeType: 'consultation_fee'
        );

        if ($departmentBilling->equipment_fee > 0) {
            $this->createCharge(
                checkin: $checkin,
                serviceType: 'consultation',
                serviceCode: 'equipment',
                description: "Equipment fee for {$departmentBilling->department_name}",
                amount: $departmentBilling->equipment_fee,
                chargeType: 'equipment_fee'
            );
        }

        return $consultationCharge;
    }

    public function createLabTestCharge(PatientCheckin $checkin, string $testCode, float $amount, string $testName): Charge
    {
        return $this->createCharge(
            checkin: $checkin,
            serviceType: 'laboratory',
            serviceCode: $testCode,
            description: "Lab test: {$testName}",
            amount: $amount,
            chargeType: 'lab_test'
        );
    }

    public function createMedicationCharge(PatientCheckin $checkin, string $drugCode, float $amount, string $drugName, int $quantity = 1): Charge
    {
        return $this->createCharge(
            checkin: $checkin,
            serviceType: 'pharmacy',
            serviceCode: $drugCode,
            description: "Medication: {$drugName} (Qty: {$quantity})",
            amount: $amount,
            chargeType: 'medication',
            metadata: ['quantity' => $quantity, 'drug_name' => $drugName]
        );
    }

    public function createWardCharges(PatientCheckin $checkin, string $wardType, ?string $bedNumber = null): array
    {
        $charges = [];

        $templates = WardBillingTemplate::where('is_active', true)
            ->whereJsonContains('applicable_ward_types', $wardType)
            ->get();

        foreach ($templates as $template) {
            if ($this->shouldApplyWardTemplate($template, $checkin, $wardType)) {
                $charge = $this->createChargeFromTemplate($checkin, $template, $wardType, $bedNumber);
                if ($charge) {
                    $charges[] = $charge;
                }
            }
        }

        return $charges;
    }

    public function createEmergencyCharge(PatientCheckin $checkin): ?Charge
    {
        $departmentBilling = DepartmentBilling::getForDepartment($checkin->department_id);

        if (!$departmentBilling || $departmentBilling->emergency_surcharge <= 0) {
            return null;
        }

        return $this->createCharge(
            checkin: $checkin,
            serviceType: 'consultation',
            serviceCode: 'emergency',
            description: "Emergency surcharge for {$departmentBilling->department_name}",
            amount: $departmentBilling->emergency_surcharge,
            chargeType: 'emergency_surcharge'
        );
    }

    public function canProceedWithService(PatientCheckin $checkin, string $serviceType, ?string $serviceCode = null, float $serviceAmount = 0): bool
    {
        // Check for active service access override first
        $activeOverride = $this->getActiveOverride($checkin, $serviceType, $serviceCode);
        if ($activeOverride) {
            return true;
        }

        // Check for active billing override (charges marked as owing)
        $activeBillingOverride = $this->getActiveBillingOverride($checkin, $serviceType);
        if ($activeBillingOverride) {
            return true;
        }

        // Check if patient has deposit balance (auto-pays charges)
        $patient = $checkin->patient;
        if ($patient && $patient->deposit_balance > 0) {
            return true;
        }

        // Check if patient has credit privilege and is within limit
        if ($patient && $patient->hasCreditPrivilege()) {
            // Check if adding this service would exceed credit limit
            if ($patient->canReceiveServices($serviceAmount)) {
                return true;
            }

            // Credit limit would be exceeded - block service
            return false;
        }

        // No deposit, no credit - check service charge rules
        $rule = ServiceChargeRule::where('service_type', $serviceType)
            ->where(function ($query) use ($serviceCode) {
                $query->whereNull('service_code')
                    ->orWhere('service_code', $serviceCode);
            })
            ->where('is_active', true)
            ->first();

        // If no rule or payment not mandatory, allow service
        if (!$rule || $rule->payment_required !== 'mandatory') {
            return true;
        }

        // Only check pending charges that are not voided or owing
        // Voided charges are from cancelled check-ins and shouldn't block service
        // Owing charges have been approved via override and shouldn't block service
        $charges = Charge::forPatient($checkin->id)
            ->forService($serviceType, $serviceCode)
            ->pending()
            ->notVoided()
            ->get();

        if ($charges->isEmpty()) {
            return true;
        }

        if ($rule->emergency_override_allowed && $this->isEmergencyOverride()) {
            return true;
        }

        return $charges->every(fn($charge) => $charge->canProceedWithService());
    }

    public function getPendingCharges(PatientCheckin $checkin, ?string $serviceType = null): \Illuminate\Database\Eloquent\Collection
    {
        // Exclude voided charges from cancelled check-ins
        $query = Charge::forPatient($checkin->id)->pending()->notVoided();

        if ($serviceType) {
            $query->where('service_type', $serviceType);
        }

        return $query->get();
    }

    public function getTotalPendingAmount(PatientCheckin $checkin): float
    {
        // Exclude voided charges from cancelled check-ins
        return Charge::forPatient($checkin->id)
            ->pending()
            ->notVoided()
            ->sum('amount');
    }

    public function markChargeAsPaid(int $chargeId, ?float $amount = null): bool
    {
        $charge = Charge::find($chargeId);

        if (!$charge) {
            return false;
        }

        $charge->markAsPaid($amount);

        return true;
    }

    private function createCharge(
        PatientCheckin $checkin,
        string $serviceType,
        string $description,
        float $amount,
        string $chargeType,
        ?string $serviceCode = null,
        ?array $metadata = null
    ): Charge {
        // Determine initial status - auto-mark as owing for credit-eligible patients
        $status = 'pending';
        $notes = null;

        if ($this->isPatientCreditEligible($checkin)) {
            $status = 'owing';
            $notes = 'Auto-marked as owing for credit-eligible patient';
        }

        $charge = Charge::create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => $serviceType,
            'service_code' => $serviceCode,
            'description' => $description,
            'amount' => $amount,
            'charge_type' => $chargeType,
            'status' => $status,
            'notes' => $notes,
            'charged_at' => now(),
            'metadata' => $metadata,
            'created_by_type' => Auth::user()?->getTable() ?? 'system',
            'created_by_id' => Auth::id() ?? 0,
        ]);

        // Auto-link charge to insurance claim if patient has one for this check-in
        $this->linkChargeToInsuranceClaim($charge, $checkin->id);

        return $charge;
    }


    private function createChargeFromTemplate(
        PatientCheckin $checkin,
        WardBillingTemplate $template,
        string $wardType,
        ?string $bedNumber = null
    ): ?Charge {
        $amount = $this->calculateTemplateAmount($template, $checkin, $wardType);

        if ($amount <= 0) {
            return null;
        }

        $description = $template->service_name;
        if ($bedNumber) {
            $description .= " (Bed: {$bedNumber})";
        }

        return $this->createCharge(
            checkin: $checkin,
            serviceType: 'ward',
            serviceCode: $template->service_code,
            description: $description,
            amount: $amount,
            chargeType: 'ward_bed',
            metadata: [
                'ward_type' => $wardType,
                'bed_number' => $bedNumber,
                'billing_type' => $template->billing_type,
                'template_id' => $template->id,
            ]
        );
    }

    private function shouldApplyWardTemplate(WardBillingTemplate $template, PatientCheckin $checkin, string $wardType): bool
    {
        $conditions = $template->auto_trigger_conditions;

        if (!$conditions || !isset($conditions['auto_create_charges']) || !$conditions['auto_create_charges']) {
            return false;
        }

        return true;
    }

    private function calculateTemplateAmount(WardBillingTemplate $template, PatientCheckin $checkin, string $wardType): float
    {
        $baseAmount = $template->base_amount;

        $patientCategory = $this->getPatientCategory($checkin);
        $categoryRules = $template->patient_category_rules[$patientCategory] ?? null;

        if ($categoryRules && isset($categoryRules['discount_percentage'])) {
            $discount = $categoryRules['discount_percentage'] / 100;
            $baseAmount = $baseAmount * (1 - $discount);
        }

        if ($categoryRules && isset($categoryRules['surcharge_percentage'])) {
            $surcharge = $categoryRules['surcharge_percentage'] / 100;
            $baseAmount = $baseAmount * (1 + $surcharge);
        }

        return round($baseAmount, 2);
    }

    private function getPatientCategory(PatientCheckin $checkin): string
    {
        return 'general';
    }

    private function isEmergencyOverride(): bool
    {
        return request()->boolean('emergency_override') ||
            session()->get('emergency_override', false);
    }

    /**
     * Get active service access override for a patient check-in and service.
     */
    public function getActiveOverride(PatientCheckin $checkin, string $serviceType, ?string $serviceCode = null): ?ServiceAccessOverride
    {
        $query = ServiceAccessOverride::active()
            ->where('patient_checkin_id', $checkin->id)
            ->forService($serviceType, $serviceCode);

        return $query->first();
    }

    /**
     * Get active billing override for a patient check-in and service type.
     */
    public function getActiveBillingOverride(PatientCheckin $checkin, string $serviceType): ?BillingOverride
    {
        return BillingOverride::active()
            ->forCheckin($checkin->id)
            ->forServiceType($serviceType)
            ->first();
    }

    /**
     * Check if patient is credit-eligible (has deposit or credit privilege).
     * Uses the unified PatientAccount system.
     */
    public function isPatientCreditEligible(PatientCheckin $checkin): bool
    {
        $patient = $checkin->patient;

        if (!$patient) {
            return false;
        }

        // Check PatientAccount for deposit or credit privilege
        if ($patient->hasCreditPrivilege()) {
            return true;
        }

        // Check if patient has deposit balance
        if ($patient->deposit_balance > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if patient can receive a service of given amount.
     * Considers deposit balance and credit limit.
     */
    public function canPatientAffordService(PatientCheckin $checkin, float $amount): bool
    {
        $patient = $checkin->patient;

        if (!$patient) {
            return false;
        }

        return $patient->canReceiveServices($amount);
    }

    /**
     * Get all active overrides for a patient check-in.
     */
    public function getActiveOverrides(PatientCheckin $checkin): Collection
    {
        return ServiceAccessOverride::active()
            ->where('patient_checkin_id', $checkin->id)
            ->with('authorizedBy:id,name')
            ->get();
    }

    /**
     * Check if a specific service is blocked due to unpaid charges.
     */
    public function isServiceBlocked(PatientCheckin $checkin, string $serviceType, ?string $serviceCode = null): bool
    {
        return !$this->canProceedWithService($checkin, $serviceType, $serviceCode);
    }

    /**
     * Get the reason why a service is blocked.
     */
    public function getServiceBlockReason(PatientCheckin $checkin, string $serviceType, ?string $serviceCode = null): ?string
    {
        if ($this->canProceedWithService($checkin, $serviceType, $serviceCode)) {
            return null;
        }

        $pendingCharges = $this->getPendingCharges($checkin, $serviceType);
        $totalPending = $pendingCharges->sum('amount');

        if ($totalPending > 0) {
            return 'Outstanding payment of GHS ' . number_format($totalPending, 2) . " required for {$serviceType} service";
        }

        return 'Service blocked due to billing requirements';
    }

    /**
     * Check if NHIS patient should skip consultation fee.
     * When enabled, NHIS patients are only charged consultation fee once per lifetime.
     */
    private function shouldSkipNhisConsultationFee(PatientCheckin $checkin): bool
    {
        // Check if the feature is enabled
        if (!BillingConfiguration::getValue('nhis_consultation_fee_once_per_lifetime', false)) {
            return false;
        }

        $patient = $checkin->patient;

        if (!$patient) {
            return false;
        }

        // Check if patient has active NHIS insurance
        if (!$patient->hasValidNhis()) {
            return false;
        }

        // Check if patient has ever been charged a consultation fee before
        return $this->hasNhisConsultationFeeAlreadyCharged($patient->id);
    }

    /**
     * Check if an NHIS patient has already been charged a consultation fee.
     */
    private function hasNhisConsultationFeeAlreadyCharged(int $patientId): bool
    {
        return Charge::whereHas('patientCheckin', function ($query) use ($patientId) {
            $query->where('patient_id', $patientId);
        })
            ->where('charge_type', 'consultation_fee')
            ->where('status', '!=', 'voided')
            ->exists();
    }

    /**
     * Link a charge to its insurance claim if one exists for the check-in.
     * This ensures lab tests and other charges are added to claims automatically.
     */
    private function linkChargeToInsuranceClaim(Charge $charge, int $patientCheckinId): void
    {
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

        // Link the charge to the claim using InsuranceClaimService
        $claimService = app(\App\Services\InsuranceClaimService::class);
        $claimService->addChargesToClaim($claim, [$charge->id]);
    }
}

