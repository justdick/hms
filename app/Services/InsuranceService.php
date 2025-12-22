<?php

namespace App\Services;

use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceTariff;
use App\Models\PatientInsurance;
use Carbon\Carbon;

class InsuranceService
{
    public function __construct(
        protected InsuranceCoverageService $coverageService
    ) {}

    /**
     * Verify if a patient has active insurance coverage
     */
    public function verifyEligibility(int $patientId, ?Carbon $date = null): ?PatientInsurance
    {
        $date = $date ?? now();

        return PatientInsurance::where('patient_id', $patientId)
            ->where('status', 'active')
            ->where('coverage_start_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('coverage_end_date')
                    ->orWhere('coverage_end_date', '>=', $date);
            })
            ->with(['plan.provider', 'plan.coverageRules'])
            ->first();
    }

    /**
     * Calculate coverage for a specific item
     *
     * @param  string  $itemType  (consultation, drug, lab, procedure, ward, nursing)
     * @return array{
     *     is_covered: bool,
     *     coverage_type: string,
     *     coverage_percentage: float,
     *     insurance_tariff: float,
     *     subtotal: float,
     *     insurance_pays: float,
     *     patient_pays: float,
     *     requires_preauthorization: bool,
     *     coverage_rule: ?InsuranceCoverageRule,
     *     exceeded_limit: bool,
     *     limit_message: ?string,
     *     is_unmapped?: bool,
     *     has_flexible_copay?: bool
     * }
     */
    public function calculateCoverage(
        PatientInsurance $patientInsurance,
        string $itemType,
        string $itemCode,
        float $standardPrice,
        int $quantity = 1,
        ?Carbon $date = null,
        ?int $itemId = null
    ): array {
        $date = $date ?? now();
        $plan = $patientInsurance->plan;

        // For NHIS plans, delegate to InsuranceCoverageService which handles
        // NHIS tariff lookup and flexible copay for unmapped items
        if ($this->coverageService->isNhisPlan($plan->id) && $itemId !== null) {
            $nhisItemType = $this->mapItemTypeToNhisType($itemType);
            $coverageCategory = $this->mapItemTypeToCoverageCategory($itemType);
            $result = $this->coverageService->calculateNhisCoverage(
                insurancePlanId: $plan->id,
                category: $coverageCategory,
                itemCode: $itemCode,
                itemId: $itemId,
                itemType: $nhisItemType,
                amount: $standardPrice,
                quantity: $quantity,
                date: $date
            );

            // Map the result to the expected format
            return [
                'is_covered' => $result['is_covered'],
                'coverage_type' => $result['coverage_type'],
                'coverage_percentage' => $result['coverage_percentage'],
                'insurance_tariff' => $result['insurance_tariff'],
                'subtotal' => $result['subtotal'],
                'insurance_pays' => $result['insurance_pays'],
                'patient_pays' => $result['patient_pays'],
                'requires_preauthorization' => $result['requires_preauthorization'],
                'coverage_rule' => $result['rule_id'] ? InsuranceCoverageRule::find($result['rule_id']) : null,
                'exceeded_limit' => $result['exceeded_limit'],
                'limit_message' => $result['limit_message'],
                'is_unmapped' => $result['is_unmapped'] ?? false,
                'has_flexible_copay' => $result['has_flexible_copay'] ?? false,
            ];
        }

        // Get the applicable tariff (if any)
        $tariff = $this->getApplicableTariff($plan->id, $itemType, $itemCode, $date);
        $effectivePrice = $tariff ? $tariff->insurance_tariff : $standardPrice;
        $subtotal = $effectivePrice * $quantity;

        // Get coverage rule
        $coverageRule = $this->getCoverageRule($plan->id, $itemType, $itemCode, $date);

        // Default to no coverage
        if (! $coverageRule || ! $coverageRule->is_covered) {
            return [
                'is_covered' => false,
                'coverage_type' => 'excluded',
                'coverage_percentage' => 0.00,
                'insurance_tariff' => $effectivePrice,
                'subtotal' => $subtotal,
                'insurance_pays' => 0.00,
                'patient_pays' => $subtotal,
                'requires_preauthorization' => false,
                'coverage_rule' => $coverageRule,
                'exceeded_limit' => false,
                'limit_message' => null,
            ];
        }

        // Check quantity limits
        $exceededLimit = false;
        $limitMessage = null;

        if ($coverageRule->max_quantity_per_visit && $quantity > $coverageRule->max_quantity_per_visit) {
            $exceededLimit = true;
            $limitMessage = "Quantity {$quantity} exceeds plan limit of {$coverageRule->max_quantity_per_visit} per visit";
        }

        // Calculate coverage based on type
        $insurancePays = 0.00;
        $patientPays = $subtotal;
        $coveragePercentage = 0.00;

        switch ($coverageRule->coverage_type) {
            case 'full':
                $insurancePays = $subtotal;
                $patientPays = 0.00;
                $coveragePercentage = 100.00;
                break;

            case 'percentage':
                $coveragePercentage = $coverageRule->coverage_value ?? 0.00;
                $insurancePays = $subtotal * ($coveragePercentage / 100);
                $patientPays = $subtotal - $insurancePays;

                // Apply patient copay if specified
                if ($coverageRule->patient_copay_percentage > 0) {
                    $patientCopay = $subtotal * ($coverageRule->patient_copay_percentage / 100);
                    $insurancePays = $subtotal - $patientCopay;
                    $patientPays = $patientCopay;
                    $coveragePercentage = 100 - $coverageRule->patient_copay_percentage;
                }
                break;

            case 'fixed':
                $fixedCoverage = $coverageRule->coverage_value ?? 0.00;
                $insurancePays = min($fixedCoverage * $quantity, $subtotal);
                $patientPays = $subtotal - $insurancePays;
                $coveragePercentage = $subtotal > 0 ? ($insurancePays / $subtotal) * 100 : 0;
                break;

            case 'excluded':
                $insurancePays = 0.00;
                $patientPays = $subtotal;
                $coveragePercentage = 0.00;
                break;
        }

        // Check amount limit per visit
        if ($coverageRule->max_amount_per_visit && $insurancePays > $coverageRule->max_amount_per_visit) {
            $exceededLimit = true;
            $limitMessage = "Insurance coverage amount exceeds plan limit of {$coverageRule->max_amount_per_visit} per visit";
            $insurancePays = $coverageRule->max_amount_per_visit;
            $patientPays = $subtotal - $insurancePays;
        }

        // Round to 2 decimal places
        $insurancePays = round($insurancePays, 2);
        $patientPays = round($patientPays, 2);

        return [
            'is_covered' => true,
            'coverage_type' => $coverageRule->coverage_type,
            'coverage_percentage' => round($coveragePercentage, 2),
            'insurance_tariff' => $effectivePrice,
            'subtotal' => $subtotal,
            'insurance_pays' => $insurancePays,
            'patient_pays' => $patientPays,
            'requires_preauthorization' => $coverageRule->requires_preauthorization ?? false,
            'coverage_rule' => $coverageRule,
            'exceeded_limit' => $exceededLimit,
            'limit_message' => $limitMessage,
        ];
    }

    /**
     * Map item type to NHIS item type for tariff lookup.
     */
    protected function mapItemTypeToNhisType(string $itemType): string
    {
        return match ($itemType) {
            'drug', 'pharmacy', 'medication' => 'drug',
            'lab', 'laboratory' => 'lab_service',
            'consultation' => 'consultation',
            'procedure', 'minor_procedure' => 'procedure',
            default => $itemType,
        };
    }

    /**
     * Map item type to coverage category for coverage rule lookup.
     */
    protected function mapItemTypeToCoverageCategory(string $itemType): string
    {
        return match ($itemType) {
            'drug', 'pharmacy', 'medication' => 'drug',
            'lab', 'laboratory' => 'lab',
            'consultation' => 'consultation',
            'procedure', 'minor_procedure' => 'procedure',
            default => $itemType,
        };
    }

    /**
     * Get applicable coverage rule for an item
     */
    public function getCoverageRule(
        int $planId,
        string $itemType,
        string $itemCode,
        ?Carbon $date = null
    ): ?InsuranceCoverageRule {
        $date = $date ?? now();

        // First try to find specific item code rule
        $rule = InsuranceCoverageRule::where('insurance_plan_id', $planId)
            ->where('coverage_category', $itemType)
            ->where('item_code', $itemCode)
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->whereNull('effective_from')
                        ->orWhere('effective_from', '<=', $date);
                })
                    ->where(function ($q) use ($date) {
                        $q->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $date);
                    });
            })
            ->first();

        if ($rule) {
            return $rule;
        }

        // Fall back to general category rule (null item_code means applies to all)
        return InsuranceCoverageRule::where('insurance_plan_id', $planId)
            ->where('coverage_category', $itemType)
            ->whereNull('item_code')
            ->where('is_active', true)
            ->where(function ($query) use ($date) {
                $query->where(function ($q) use ($date) {
                    $q->whereNull('effective_from')
                        ->orWhere('effective_from', '<=', $date);
                })
                    ->where(function ($q) use ($date) {
                        $q->whereNull('effective_to')
                            ->orWhere('effective_to', '>=', $date);
                    });
            })
            ->first();
    }

    /**
     * Get applicable tariff for an item
     */
    public function getApplicableTariff(
        int $planId,
        string $itemType,
        string $itemCode,
        ?Carbon $date = null
    ): ?InsuranceTariff {
        $date = $date ?? now();

        return InsuranceTariff::where('insurance_plan_id', $planId)
            ->where('item_type', $itemType)
            ->where('item_code', $itemCode)
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Check if a patient has reached their annual limit
     */
    public function checkAnnualLimit(PatientInsurance $patientInsurance, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $plan = $patientInsurance->plan;

        if (! $plan->annual_limit) {
            return [
                'has_limit' => false,
                'annual_limit' => null,
                'used_amount' => 0.00,
                'remaining_amount' => null,
                'is_exceeded' => false,
            ];
        }

        // Calculate total claims for the year
        $usedAmount = $patientInsurance->claims()
            ->whereYear('date_of_attendance', $year)
            ->whereIn('status', ['approved', 'paid', 'partial'])
            ->sum('insurance_covered_amount');

        $remainingAmount = $plan->annual_limit - $usedAmount;
        $isExceeded = $remainingAmount <= 0;

        return [
            'has_limit' => true,
            'annual_limit' => $plan->annual_limit,
            'used_amount' => round($usedAmount, 2),
            'remaining_amount' => round(max(0, $remainingAmount), 2),
            'is_exceeded' => $isExceeded,
        ];
    }

    /**
     * Check if a patient has reached their visit limit
     */
    public function checkVisitLimit(PatientInsurance $patientInsurance, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $plan = $patientInsurance->plan;

        if (! $plan->visit_limit) {
            return [
                'has_limit' => false,
                'visit_limit' => null,
                'used_visits' => 0,
                'remaining_visits' => null,
                'is_exceeded' => false,
            ];
        }

        // Count visits for the year
        $usedVisits = $patientInsurance->claims()
            ->whereYear('date_of_attendance', $year)
            ->whereIn('status', ['approved', 'paid', 'partial', 'submitted', 'vetted'])
            ->count();

        $remainingVisits = $plan->visit_limit - $usedVisits;
        $isExceeded = $remainingVisits <= 0;

        return [
            'has_limit' => true,
            'visit_limit' => $plan->visit_limit,
            'used_visits' => $usedVisits,
            'remaining_visits' => max(0, $remainingVisits),
            'is_exceeded' => $isExceeded,
        ];
    }

    /**
     * Batch calculate coverage for multiple items
     *
     * @param  array  $items  Array of ['type', 'code', 'price', 'quantity']
     */
    public function calculateBatchCoverage(PatientInsurance $patientInsurance, array $items): array
    {
        $results = [];
        $totalSubtotal = 0.00;
        $totalInsurancePays = 0.00;
        $totalPatientPays = 0.00;

        foreach ($items as $item) {
            $coverage = $this->calculateCoverage(
                $patientInsurance,
                $item['type'],
                $item['code'],
                $item['price'],
                $item['quantity'] ?? 1
            );

            $results[] = array_merge($item, $coverage);
            $totalSubtotal += $coverage['subtotal'];
            $totalInsurancePays += $coverage['insurance_pays'];
            $totalPatientPays += $coverage['patient_pays'];
        }

        return [
            'items' => $results,
            'summary' => [
                'total_subtotal' => round($totalSubtotal, 2),
                'total_insurance_pays' => round($totalInsurancePays, 2),
                'total_patient_pays' => round($totalPatientPays, 2),
            ],
        ];
    }
}
