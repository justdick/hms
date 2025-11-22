<?php

namespace App\Services;

use App\Models\BillingConfiguration;
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
        if (! BillingConfiguration::getValue('auto_billing_enabled', true)) {
            return null;
        }

        $departmentBilling = DepartmentBilling::getForDepartment($checkin->department_id);

        if (! $departmentBilling) {
            return null;
        }

        $consultationCharge = $this->createCharge(
            checkin: $checkin,
            serviceType: 'consultation',
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

        if (! $departmentBilling || $departmentBilling->emergency_surcharge <= 0) {
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

    public function canProceedWithService(PatientCheckin $checkin, string $serviceType, ?string $serviceCode = null): bool
    {
        // Check for active service access override first
        $activeOverride = $this->getActiveOverride($checkin, $serviceType, $serviceCode);
        if ($activeOverride) {
            return true;
        }

        $rule = ServiceChargeRule::where('service_type', $serviceType)
            ->where(function ($query) use ($serviceCode) {
                $query->whereNull('service_code')
                    ->orWhere('service_code', $serviceCode);
            })
            ->where('is_active', true)
            ->first();

        if (! $rule || $rule->payment_required !== 'mandatory') {
            return true;
        }

        // Only check pending charges that are not voided
        // Voided charges are from cancelled check-ins and shouldn't block service
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

        return $charges->every(fn ($charge) => $charge->canProceedWithService());
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

        if (! $charge) {
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
        return Charge::create([
            'patient_checkin_id' => $checkin->id,
            'service_type' => $serviceType,
            'service_code' => $serviceCode,
            'description' => $description,
            'amount' => $amount,
            'charge_type' => $chargeType,
            'charged_at' => now(),
            'metadata' => $metadata,
            'created_by_type' => Auth::user()?->getTable() ?? 'system',
            'created_by_id' => Auth::id() ?? 0,
        ]);
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

        if (! $conditions || ! isset($conditions['auto_create_charges']) || ! $conditions['auto_create_charges']) {
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
        return ! $this->canProceedWithService($checkin, $serviceType, $serviceCode);
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
            return 'Outstanding payment of GHS '.number_format($totalPending, 2)." required for {$serviceType} service";
        }

        return 'Service blocked due to billing requirements';
    }
}
