<?php

namespace App\Services;

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceTariff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class InsuranceCoverageService
{
    public function __construct(
        protected NhisTariffService $nhisTariffService
    ) {}

    /**
     * Check if an insurance plan belongs to an NHIS provider.
     */
    public function isNhisPlan(int $insurancePlanId): bool
    {
        $cacheKey = "is_nhis_plan_{$insurancePlanId}";

        return Cache::remember($cacheKey, 3600, function () use ($insurancePlanId) {
            $plan = InsurancePlan::with('provider')->find($insurancePlanId);

            if (! $plan || ! $plan->provider) {
                return false;
            }

            return $plan->provider->isNhis();
        });
    }

    /**
     * Calculate coverage for NHIS patients.
     * Uses NHIS Tariff Master prices and only copay from coverage rules.
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
     *     limit_message: ?string,
     *     nhis_code: ?string,
     *     is_nhis: bool,
     *     is_unmapped: bool,
     *     has_flexible_copay: bool
     * }
     */
    public function calculateNhisCoverage(
        int $insurancePlanId,
        string $category,
        string $itemCode,
        int $itemId,
        string $itemType,
        float $amount,
        int $quantity = 1,
        ?Carbon $date = null
    ): array {
        $date = $date ?? now();

        // Look up NHIS tariff from Master via mapping
        $nhisTariff = $this->nhisTariffService->getTariffForItem($itemType, $itemId);

        // If item is not mapped to NHIS, check for copay rules
        if (! $nhisTariff) {
            $subtotal = $amount * $quantity;

            // First check for flexible copay rule (unmapped item with is_unmapped flag)
            $flexibleCopayRule = $this->getFlexibleCopayRule($insurancePlanId, $category, $itemCode, $date);

            if ($flexibleCopayRule && $flexibleCopayRule->patient_copay_amount !== null) {
                $copayAmount = (float) $flexibleCopayRule->patient_copay_amount * $quantity;

                return [
                    'is_covered' => true,
                    'insurance_pays' => 0.00,
                    'patient_pays' => $copayAmount,
                    'coverage_percentage' => 0.00,
                    'rule_type' => 'flexible_copay',
                    'rule_id' => $flexibleCopayRule->id,
                    'coverage_type' => 'nhis_unmapped_with_copay',
                    'insurance_tariff' => $amount,
                    'subtotal' => $subtotal,
                    'requires_preauthorization' => $flexibleCopayRule->requires_preauthorization ?? false,
                    'exceeded_limit' => false,
                    'limit_message' => null,
                    'nhis_code' => null,
                    'is_nhis' => true,
                    'is_unmapped' => true,
                    'has_flexible_copay' => true,
                ];
            }

            // Also check regular coverage rule - if it has patient_copay_amount, use it
            // This handles items like consultations where copay is set but is_unmapped may not be true
            $regularRule = $this->getCoverageRule($insurancePlanId, $category, $itemCode, $date);

            if ($regularRule && $regularRule->patient_copay_amount !== null && (float) $regularRule->patient_copay_amount > 0) {
                $copayAmount = (float) $regularRule->patient_copay_amount * $quantity;

                return [
                    'is_covered' => true,
                    'insurance_pays' => 0.00,
                    'patient_pays' => $copayAmount,
                    'coverage_percentage' => 0.00,
                    'rule_type' => $regularRule->item_code ? 'specific' : 'general',
                    'rule_id' => $regularRule->id,
                    'coverage_type' => 'nhis_unmapped_with_copay',
                    'insurance_tariff' => $amount,
                    'subtotal' => $subtotal,
                    'requires_preauthorization' => $regularRule->requires_preauthorization ?? false,
                    'exceeded_limit' => false,
                    'limit_message' => null,
                    'nhis_code' => null,
                    'is_nhis' => true,
                    'is_unmapped' => true,
                    'has_flexible_copay' => true,
                ];
            }

            // Unmapped item without any copay rule: patient pays full cash price
            return [
                'is_covered' => false,
                'insurance_pays' => 0.00,
                'patient_pays' => $subtotal,
                'coverage_percentage' => 0.00,
                'rule_type' => 'none',
                'rule_id' => null,
                'coverage_type' => 'nhis_not_mapped',
                'insurance_tariff' => $amount,
                'subtotal' => $subtotal,
                'requires_preauthorization' => false,
                'exceeded_limit' => false,
                'limit_message' => null,
                'nhis_code' => null,
                'is_nhis' => true,
                'is_unmapped' => true,
                'has_flexible_copay' => false,
            ];
        }

        // Use NHIS tariff price as the effective price
        $effectivePrice = (float) $nhisTariff->price;
        $subtotal = $effectivePrice * $quantity;

        // Get coverage rule for copay amount only
        $rule = $this->getCoverageRule($insurancePlanId, $category, $itemCode, $date);

        // Calculate copay from coverage rule (only fixed copay for NHIS)
        $copayAmount = 0.00;
        if ($rule && $rule->patient_copay_amount) {
            $copayAmount = (float) $rule->patient_copay_amount * $quantity;
        }

        // For NHIS: insurance pays the NHIS tariff price, patient pays only copay
        $insurancePays = $subtotal;
        $patientPays = $copayAmount;

        // Check limits if rule exists
        $exceededLimit = false;
        $limitMessage = null;
        $requiresPreauthorization = false;

        if ($rule) {
            $requiresPreauthorization = $rule->requires_preauthorization ?? false;

            if ($rule->max_quantity_per_visit && $quantity > $rule->max_quantity_per_visit) {
                $exceededLimit = true;
                $limitMessage = "Quantity {$quantity} exceeds plan limit of {$rule->max_quantity_per_visit} per visit";
            }

            if ($rule->max_amount_per_visit && $insurancePays > $rule->max_amount_per_visit) {
                $exceededLimit = true;
                $limitMessage = "Insurance coverage amount exceeds plan limit of {$rule->max_amount_per_visit} per visit";
                $insurancePays = $rule->max_amount_per_visit;
                // Patient pays the difference plus copay
                $patientPays = ($subtotal - $insurancePays) + $copayAmount;
            }
        }

        // Round to 2 decimal places
        $insurancePays = round($insurancePays, 2);
        $patientPays = round($patientPays, 2);

        // Calculate effective coverage percentage
        $coveragePercentage = $subtotal > 0 ? ($insurancePays / $subtotal) * 100 : 0;

        return [
            'is_covered' => true,
            'insurance_pays' => $insurancePays,
            'patient_pays' => $patientPays,
            'coverage_percentage' => round($coveragePercentage, 2),
            'rule_type' => $rule ? ($rule->item_code ? 'specific' : 'general') : 'nhis_default',
            'rule_id' => $rule?->id,
            'coverage_type' => 'nhis',
            'insurance_tariff' => $effectivePrice,
            'subtotal' => $subtotal,
            'requires_preauthorization' => $requiresPreauthorization,
            'exceeded_limit' => $exceededLimit,
            'limit_message' => $limitMessage,
            'nhis_code' => $nhisTariff->nhis_code,
            'is_nhis' => true,
            'is_unmapped' => false,
            'has_flexible_copay' => false,
        ];
    }

    /**
     * Get flexible copay rule for an unmapped NHIS item.
     */
    protected function getFlexibleCopayRule(
        int $insurancePlanId,
        string $category,
        string $itemCode,
        Carbon $date
    ): ?InsuranceCoverageRule {
        $cacheKey = "flexible_copay_rule_{$insurancePlanId}_{$category}_{$itemCode}";

        $rules = Cache::remember($cacheKey, 3600, function () use ($insurancePlanId, $category, $itemCode) {
            return InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $category)
                ->where('item_code', $itemCode)
                ->where('is_unmapped', true)
                ->where('is_active', true)
                ->orderBy('effective_from', 'desc')
                ->get();
        });

        // Filter by date in memory
        return $rules->filter(function ($rule) use ($date) {
            $effectiveFrom = $rule->effective_from ? $rule->effective_from->startOfDay() : null;
            $effectiveTo = $rule->effective_to ? $rule->effective_to->endOfDay() : null;

            $afterStart = ! $effectiveFrom || $effectiveFrom->lte($date);
            $beforeEnd = ! $effectiveTo || $effectiveTo->gte($date);

            return $afterStart && $beforeEnd;
        })->first();
    }

    /**
     * Check if item has flexible copay configured.
     */
    public function hasFlexibleCopay(
        int $insurancePlanId,
        string $category,
        string $itemCode
    ): bool {
        $rule = $this->getFlexibleCopayRule($insurancePlanId, $category, $itemCode, now());

        return $rule !== null && $rule->patient_copay_amount !== null;
    }

    /**
     * Get flexible copay amount for unmapped item.
     */
    public function getFlexibleCopayAmount(
        int $insurancePlanId,
        string $category,
        string $itemCode
    ): ?float {
        $rule = $this->getFlexibleCopayRule($insurancePlanId, $category, $itemCode, now());

        return $rule?->patient_copay_amount ? (float) $rule->patient_copay_amount : null;
    }

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
        $rule = $rules->filter(function ($rule) use ($date) {
            $effectiveFrom = $rule->effective_from ? $rule->effective_from->startOfDay() : null;
            $effectiveTo = $rule->effective_to ? $rule->effective_to->endOfDay() : null;

            $afterStart = ! $effectiveFrom || $effectiveFrom->lte($date);
            $beforeEnd = ! $effectiveTo || $effectiveTo->gte($date);

            return $afterStart && $beforeEnd;
        })->first();

        // If no explicit rule found, check for category defaults on the plan
        if (! $rule) {
            $rule = $this->createRuleFromCategoryDefault($insurancePlanId, $category);
        }

        return $rule;
    }

    /**
     * Create a virtual coverage rule from the plan's category default.
     * This returns a non-persisted InsuranceCoverageRule object for calculation purposes.
     */
    protected function createRuleFromCategoryDefault(
        int $insurancePlanId,
        string $category
    ): ?InsuranceCoverageRule {
        $cacheKey = "plan_category_defaults_{$insurancePlanId}";

        $plan = Cache::remember($cacheKey, 3600, function () use ($insurancePlanId) {
            return InsurancePlan::find($insurancePlanId);
        });

        if (! $plan) {
            return null;
        }

        // Map category to plan's default field
        $defaultValue = $this->getCategoryDefaultFromPlan($plan, $category);

        if ($defaultValue === null) {
            return null;
        }

        // Create a virtual (non-persisted) coverage rule
        $rule = new InsuranceCoverageRule;
        $rule->insurance_plan_id = $insurancePlanId;
        $rule->coverage_category = $category;
        $rule->item_code = null;
        $rule->coverage_type = 'percentage';
        $rule->coverage_value = $defaultValue;
        $rule->patient_copay_percentage = 100 - $defaultValue;
        $rule->is_covered = $defaultValue > 0;
        $rule->is_active = true;
        // Mark this as a virtual rule (not from database)
        $rule->id = null;

        return $rule;
    }

    /**
     * Get the category default percentage from the plan.
     */
    protected function getCategoryDefaultFromPlan(InsurancePlan $plan, string $category): ?float
    {
        return match ($category) {
            'consultation' => $plan->consultation_default !== null ? (float) $plan->consultation_default : null,
            'drug' => $plan->drugs_default !== null ? (float) $plan->drugs_default : null,
            'lab' => $plan->labs_default !== null ? (float) $plan->labs_default : null,
            'procedure' => $plan->procedures_default !== null ? (float) $plan->procedures_default : null,
            // Ward and nursing don't have category defaults on the plan, fall back to null
            'ward', 'nursing' => null,
            default => null,
        };
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
     *     limit_message: ?string,
     *     nhis_code?: ?string,
     *     is_nhis?: bool
     * }
     */
    public function calculateCoverage(
        int $insurancePlanId,
        string $category,
        string $itemCode,
        float $amount,
        int $quantity = 1,
        ?Carbon $date = null,
        ?int $itemId = null,
        ?string $itemType = null
    ): array {
        $date = $date ?? now();

        // Check if this is an NHIS plan and we have item details for NHIS lookup
        if ($itemId !== null && $itemType !== null && $this->isNhisPlan($insurancePlanId)) {
            return $this->calculateNhisCoverage(
                $insurancePlanId,
                $category,
                $itemCode,
                $itemId,
                $itemType,
                $amount,
                $quantity,
                $date
            );
        }

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

            // Also clear flexible copay cache
            $flexibleCopayKey = "flexible_copay_rule_{$insurancePlanId}_{$category}_{$itemCode}";
            Cache::forget($flexibleCopayKey);
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

        // Also clear NHIS plan cache and category defaults cache
        Cache::forget("is_nhis_plan_{$insurancePlanId}");
        Cache::forget("plan_category_defaults_{$insurancePlanId}");
    }

    /**
     * Clear NHIS plan cache for a specific plan.
     */
    public function clearNhisPlanCache(int $insurancePlanId): void
    {
        Cache::forget("is_nhis_plan_{$insurancePlanId}");
    }
}
