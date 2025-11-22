<?php

namespace App\Services;

use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceTariff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class InsuranceCoverageService
{
    /**
     * Get the applicable coverage rule for a specific item
     * Implements the hierarchy: specific rule > general rule > no coverage
     */
    public function getCoverageRule(
        int $insurancePlanId,
        string $category,
        ?string $itemCode = null,
        ?Carbon $date = null
    ): ?InsuranceCoverageRule {
        $date = $date ?? now();

        // First, try to find item-specific rule
        if ($itemCode) {
            $specificRule = $this->findSpecificRule($insurancePlanId, $category, $itemCode, $date);

            if ($specificRule) {
                return $specificRule;
            }
        }

        // Fall back to general rule for category
        return $this->findGeneralRule($insurancePlanId, $category, $date);
    }

    /**
     * Find a specific coverage rule for an item
     */
    protected function findSpecificRule(
        int $insurancePlanId,
        string $category,
        string $itemCode,
        Carbon $date
    ): ?InsuranceCoverageRule {
        $cacheKey = "coverage_rule_specific_{$insurancePlanId}_{$category}_{$itemCode}";

        // Get all rules from cache or database
        $rules = Cache::remember($cacheKey, 3600, function () use ($insurancePlanId, $category, $itemCode) {
            return InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $category)
                ->where('item_code', $itemCode)
                ->where('is_active', true)
                ->orderBy('effective_from', 'desc')
                ->get();
        });

        // Filter by date in memory to avoid cache issues with different dates
        return $rules->filter(function ($rule) use ($date) {
            $effectiveFrom = $rule->effective_from ? $rule->effective_from->startOfDay() : null;
            $effectiveTo = $rule->effective_to ? $rule->effective_to->endOfDay() : null;

            $afterStart = ! $effectiveFrom || $effectiveFrom->lte($date);
            $beforeEnd = ! $effectiveTo || $effectiveTo->gte($date);

            return $afterStart && $beforeEnd;
        })->first();
    }

    /**
     * Find a general coverage rule for a category
     */
    protected function findGeneralRule(
        int $insurancePlanId,
        string $category,
        Carbon $date
    ): ?InsuranceCoverageRule {
        $cacheKey = "coverage_rule_general_{$insurancePlanId}_{$category}";

        // Get all rules from cache or database
        $rules = Cache::remember($cacheKey, 3600, function () use ($insurancePlanId, $category) {
            return InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $category)
                ->whereNull('item_code')
                ->where('is_active', true)
                ->orderBy('effective_from', 'desc')
                ->get();
        });

        // Filter by date in memory to avoid cache issues with different dates
        return $rules->filter(function ($rule) use ($date) {
            $effectiveFrom = $rule->effective_from ? $rule->effective_from->startOfDay() : null;
            $effectiveTo = $rule->effective_to ? $rule->effective_to->endOfDay() : null;

            $afterStart = ! $effectiveFrom || $effectiveFrom->lte($date);
            $beforeEnd = ! $effectiveTo || $effectiveTo->gte($date);

            return $afterStart && $beforeEnd;
        })->first();
    }

    /**
     * Calculate coverage amounts for an item
     *
     * @return array{
     *     is_covered: bool,
     *     insurance_pays: float,
     *     patient_pays: float,
     *     coverage_percentage: float,
     *     rule_type: string,
     *     rule_id: ?int,
     *     coverage_type: string,
     *     insurance_tariff: float,
     *     subtotal: float,
     *     requires_preauthorization: bool,
     *     exceeded_limit: bool,
     *     limit_message: ?string
     * }
     */
    public function calculateCoverage(
        int $insurancePlanId,
        string $category,
        string $itemCode,
        float $amount,
        int $quantity = 1,
        ?Carbon $date = null
    ): array {
        $date = $date ?? now();

        // Get the applicable coverage rule
        $rule = $this->getCoverageRule($insurancePlanId, $category, $itemCode, $date);

        // Determine effective price: tariff_amount from rule > tariff table > standard amount
        $effectivePrice = (float) $amount; // Default to standard price

        if ($rule && $rule->tariff_amount) {
            // Use tariff from coverage rule if set
            $effectivePrice = (float) $rule->tariff_amount;
        } else {
            // Fall back to tariff table
            $tariff = $this->getInsuranceTariff($insurancePlanId, $category, $itemCode, $date);
            if ($tariff) {
                $effectivePrice = (float) $tariff->insurance_tariff;
            }
        }

        $subtotal = $effectivePrice * $quantity;

        // Default to no coverage
        if (! $rule || ! $rule->is_covered) {
            return [
                'is_covered' => false,
                'insurance_pays' => 0.00,
                'patient_pays' => $subtotal,
                'coverage_percentage' => 0.00,
                'rule_type' => 'none',
                'rule_id' => null,
                'coverage_type' => 'excluded',
                'insurance_tariff' => $effectivePrice,
                'subtotal' => $subtotal,
                'requires_preauthorization' => false,
                'exceeded_limit' => false,
                'limit_message' => null,
            ];
        }

        // Check quantity limits
        $exceededLimit = false;
        $limitMessage = null;

        if ($rule->max_quantity_per_visit && $quantity > $rule->max_quantity_per_visit) {
            $exceededLimit = true;
            $limitMessage = "Quantity {$quantity} exceeds plan limit of {$rule->max_quantity_per_visit} per visit";
        }

        // Calculate coverage based on type
        $insurancePays = 0.00;
        $patientPercentagePayment = 0.00;
        $patientFixedCopay = ($rule->patient_copay_amount ?? 0) * $quantity;
        $coveragePercentage = 0.00;

        switch ($rule->coverage_type) {
            case 'full':
                $insurancePays = $subtotal;
                $patientPercentagePayment = 0.00;
                $coveragePercentage = 100.00;
                break;

            case 'percentage':
                // Use coverage_value to determine insurance payment
                $coveragePercentage = $rule->coverage_value ?? 0.00;
                $insurancePays = $subtotal * ($coveragePercentage / 100);

                // Patient pays the remainder (percentage-based)
                $patientPercentagePayment = $subtotal - $insurancePays;
                break;

            case 'fixed':
                $fixedCoverage = $rule->coverage_value ?? 0.00;
                $insurancePays = min($fixedCoverage * $quantity, $subtotal);
                $patientPercentagePayment = $subtotal - $insurancePays;
                $coveragePercentage = $subtotal > 0 ? ($insurancePays / $subtotal) * 100 : 0;
                break;

            case 'excluded':
                $insurancePays = 0.00;
                $patientPercentagePayment = $subtotal;
                $coveragePercentage = 0.00;
                break;
        }

        // Calculate total patient payment (percentage + fixed copay)
        $patientPays = $patientPercentagePayment + $patientFixedCopay;

        // Check amount limit per visit
        if ($rule->max_amount_per_visit && $insurancePays > $rule->max_amount_per_visit) {
            $exceededLimit = true;
            $limitMessage = "Insurance coverage amount exceeds plan limit of {$rule->max_amount_per_visit} per visit";
            $insurancePays = $rule->max_amount_per_visit;
            $patientPays = $subtotal - $insurancePays;
        }

        // Round to 2 decimal places
        $insurancePays = round($insurancePays, 2);
        $patientPays = round($patientPays, 2);

        return [
            'is_covered' => true,
            'insurance_pays' => $insurancePays,
            'patient_pays' => $patientPays,
            'coverage_percentage' => round($coveragePercentage, 2),
            'rule_type' => $rule->item_code ? 'specific' : 'general',
            'rule_id' => $rule->id,
            'coverage_type' => $rule->coverage_type,
            'insurance_tariff' => $effectivePrice,
            'subtotal' => $subtotal,
            'requires_preauthorization' => $rule->requires_preauthorization ?? false,
            'exceeded_limit' => $exceededLimit,
            'limit_message' => $limitMessage,
        ];
    }

    /**
     * Get applicable tariff for an item
     */
    protected function getInsuranceTariff(
        int $planId,
        string $itemType,
        string $itemCode,
        Carbon $date
    ): ?InsuranceTariff {
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
     * Clear cache for a specific coverage rule
     */
    public function clearRuleCache(int $insurancePlanId, string $category, ?string $itemCode = null): void
    {
        if ($itemCode) {
            $cacheKey = "coverage_rule_specific_{$insurancePlanId}_{$category}_{$itemCode}";
            Cache::forget($cacheKey);
        }

        $cacheKey = "coverage_rule_general_{$insurancePlanId}_{$category}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all cache for an insurance plan
     */
    public function clearPlanCache(int $insurancePlanId): void
    {
        $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];

        foreach ($categories as $category) {
            $cacheKey = "coverage_rule_general_{$insurancePlanId}_{$category}";
            Cache::forget($cacheKey);
        }
    }
}
