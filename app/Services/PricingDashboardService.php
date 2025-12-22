<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DepartmentBilling;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\PricingChangeLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PricingDashboardService
{
    /**
     * Cached NHIS mappings keyed by "item_type:item_id".
     */
    protected ?Collection $nhisMappingsCache = null;

    /**
     * Cached coverage rules keyed by "category:item_code" or "category:" for defaults.
     */
    protected ?Collection $coverageRulesCache = null;

    /**
     * Cached category default rules keyed by category.
     */
    protected ?Collection $categoryDefaultsCache = null;

    public function __construct(
        protected InsuranceCoverageService $coverageService
    ) {}

    /**
     * Get all pricing data for a specific insurance plan.
     *
     * @param  string|null  $pricingStatus  Filter by pricing status: 'unpriced', 'priced', or null for all
     * @return array{
     *     items: LengthAwarePaginator,
     *     categories: array,
     *     plan: ?InsurancePlan,
     *     is_nhis: bool
     * }
     */
    public function getPricingData(
        ?int $insurancePlanId,
        ?string $category = null,
        ?string $search = null,
        bool $unmappedOnly = false,
        ?int $perPage = null,
        ?string $pricingStatus = null
    ): array {
        // Default to 5 items per page, allow override via request
        $perPage = $perPage ?? (int) request()->get('per_page', 5);
        $plan = $insurancePlanId ? InsurancePlan::with('provider')->find($insurancePlanId) : null;
        $isNhis = $plan && $plan->provider && $plan->provider->is_nhis;

        // Pre-load all insurance data in bulk to avoid N+1 queries
        if ($insurancePlanId) {
            $this->preloadInsuranceData($insurancePlanId, $isNhis);
        }

        // Collect all pricing items
        $items = collect();

        // Get drugs
        if (! $category || $category === 'drugs') {
            $drugs = $this->getDrugPricingItems($insurancePlanId, $isNhis, $search);
            $items = $items->merge($drugs);
        }

        // Get lab services
        if (! $category || $category === 'lab') {
            $labServices = $this->getLabServicePricingItems($insurancePlanId, $isNhis, $search);
            $items = $items->merge($labServices);
        }

        // Get consultations (department billing)
        if (! $category || $category === 'consultation') {
            $consultations = $this->getConsultationPricingItems($insurancePlanId, $isNhis, $search);
            $items = $items->merge($consultations);
        }

        // Get procedures
        if (! $category || $category === 'procedure') {
            $procedures = $this->getProcedurePricingItems($insurancePlanId, $isNhis, $search);
            $items = $items->merge($procedures);
        }

        // Clear caches after use
        $this->clearCaches();

        // Filter unmapped items for NHIS
        if ($unmappedOnly && $isNhis) {
            $items = $items->filter(fn ($item) => ! $item['is_mapped']);
        }

        // Filter by pricing status
        if ($pricingStatus === 'unpriced') {
            // Unpriced items have null or zero cash price
            $items = $items->filter(fn ($item) => $item['cash_price'] === null || $item['cash_price'] <= 0);
        } elseif ($pricingStatus === 'priced') {
            // Priced items have a positive cash price
            $items = $items->filter(fn ($item) => $item['cash_price'] !== null && $item['cash_price'] > 0);
        }

        // Sort by category then name
        $items = $items->sortBy([
            ['category', 'asc'],
            ['name', 'asc'],
        ])->values();

        // Paginate manually
        $page = request()->get('page', 1);
        $offset = ($page - 1) * $perPage;
        $paginatedItems = new LengthAwarePaginator(
            $items->slice($offset, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return [
            'items' => $paginatedItems,
            'categories' => ['drugs', 'lab', 'consultation', 'procedure'],
            'plan' => $plan,
            'is_nhis' => $isNhis,
        ];
    }

    /**
     * Pre-load all insurance data (NHIS mappings and coverage rules) in bulk.
     * This eliminates N+1 queries by loading everything upfront.
     */
    protected function preloadInsuranceData(int $insurancePlanId, bool $isNhis): void
    {
        // Load all NHIS mappings with tariffs in one query
        if ($isNhis) {
            $this->nhisMappingsCache = NhisItemMapping::with('nhisTariff')
                ->whereNotNull('nhis_tariff_id')
                ->get()
                ->keyBy(fn ($mapping) => $mapping->item_type.':'.$mapping->item_id);
        }

        // Load all coverage rules for this plan in one query
        $allRules = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
            ->where('is_active', true)
            ->get();

        // Separate item-specific rules and category defaults
        $this->coverageRulesCache = $allRules
            ->whereNotNull('item_code')
            ->keyBy(fn ($rule) => $rule->coverage_category.':'.$rule->item_code);

        $this->categoryDefaultsCache = $allRules
            ->whereNull('item_code')
            ->keyBy('coverage_category');
    }

    /**
     * Clear the caches after use.
     */
    protected function clearCaches(): void
    {
        $this->nhisMappingsCache = null;
        $this->coverageRulesCache = null;
        $this->categoryDefaultsCache = null;
    }

    /**
     * Get drug pricing items.
     */
    protected function getDrugPricingItems(?int $insurancePlanId, bool $isNhis, ?string $search): Collection
    {
        $query = Drug::query()->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('drug_code', 'LIKE', "%{$search}%")
                    ->orWhere('generic_name', 'LIKE', "%{$search}%");
            });
        }

        $drugs = $query->get();

        return $drugs->map(function ($drug) use ($insurancePlanId, $isNhis) {
            return $this->buildPricingItemOptimized(
                $drug->id,
                'drug',
                $drug->drug_code,
                $drug->name,
                'drugs',
                (float) $drug->unit_price,
                $insurancePlanId,
                $isNhis,
                $drug->id,
                'drug'
            );
        });
    }

    /**
     * Get lab service pricing items.
     */
    protected function getLabServicePricingItems(?int $insurancePlanId, bool $isNhis, ?string $search): Collection
    {
        $query = LabService::query()->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $labServices = $query->get();

        return $labServices->map(function ($labService) use ($insurancePlanId, $isNhis) {
            return $this->buildPricingItemOptimized(
                $labService->id,
                'lab',
                $labService->code,
                $labService->name,
                'lab',
                (float) $labService->price,
                $insurancePlanId,
                $isNhis,
                $labService->id,
                'lab_service'
            );
        });
    }

    /**
     * Get consultation pricing items (from departments with optional billing).
     * Shows ALL departments, not just those with billing configured.
     */
    protected function getConsultationPricingItems(?int $insurancePlanId, bool $isNhis, ?string $search): Collection
    {
        $query = Department::query()
            ->active()
            ->with('billing');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $departments = $query->get();

        return $departments->map(function ($department) use ($insurancePlanId, $isNhis) {
            $billing = $department->billing;

            // Always use department ID as the identifier - we'll look up/create billing in updateCashPrice
            return $this->buildPricingItemOptimized(
                $department->id,
                'consultation',
                $department->code,
                $department->name.' Consultation',
                'consultation',
                $billing ? (float) $billing->consultation_fee : null,
                $insurancePlanId,
                $isNhis,
                $department->id,
                'consultation'
            );
        });
    }

    /**
     * Get procedure pricing items.
     */
    protected function getProcedurePricingItems(?int $insurancePlanId, bool $isNhis, ?string $search): Collection
    {
        $query = MinorProcedureType::query()->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $procedures = $query->get();

        return $procedures->map(function ($procedure) use ($insurancePlanId, $isNhis) {
            return $this->buildPricingItemOptimized(
                $procedure->id,
                'procedure',
                $procedure->code,
                $procedure->name,
                'procedure',
                (float) $procedure->price,
                $insurancePlanId,
                $isNhis,
                $procedure->id,
                'procedure'
            );
        });
    }

    /**
     * Build a pricing item array with insurance data using cached lookups.
     * This is the optimized version that uses pre-loaded data instead of per-item queries.
     */
    protected function buildPricingItemOptimized(
        int $id,
        string $type,
        ?string $code,
        string $name,
        string $category,
        ?float $cashPrice,
        ?int $insurancePlanId,
        bool $isNhis,
        int $itemId,
        string $itemType
    ): array {
        $insuranceTariff = null;
        $copayAmount = null;
        $coverageValue = null;
        $coverageType = null;
        $isMapped = true;
        $isUnmapped = false;
        $nhisCode = null;
        $coverageRuleId = null;

        if ($insurancePlanId) {
            // Get NHIS mapping from cache (no query)
            if ($isNhis && $this->nhisMappingsCache !== null) {
                $cacheKey = $itemType.':'.$itemId;
                $mapping = $this->nhisMappingsCache->get($cacheKey);
                $isMapped = $mapping && $mapping->nhisTariff;

                if ($isMapped) {
                    $insuranceTariff = (float) $mapping->nhisTariff->price;
                    $nhisCode = $mapping->nhisTariff->nhis_code;
                }
            }

            // Get coverage rule from cache (no query)
            $coverageCategory = $this->mapTypeToCoverageCategory($type);
            $rule = $this->getCoverageRuleFromCache($coverageCategory, $code);

            if ($rule) {
                $coverageRuleId = $rule->id;
                $copayAmount = $rule->patient_copay_amount ? (float) $rule->patient_copay_amount : null;
                $isUnmapped = (bool) $rule->is_unmapped;

                if (! $isNhis) {
                    $insuranceTariff = $rule->tariff_amount ? (float) $rule->tariff_amount : null;
                    $coverageValue = $rule->coverage_value ? (float) $rule->coverage_value : null;
                    $coverageType = $rule->coverage_type;
                }
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'cash_price' => $cashPrice,
            'insurance_tariff' => $insuranceTariff,
            'copay_amount' => $copayAmount,
            'coverage_value' => $coverageValue,
            'coverage_type' => $coverageType,
            'is_mapped' => $isMapped,
            'is_unmapped' => $isUnmapped,
            'nhis_code' => $nhisCode,
            'coverage_rule_id' => $coverageRuleId,
        ];
    }

    /**
     * Get coverage rule from cache - first tries item-specific, then category default.
     */
    protected function getCoverageRuleFromCache(string $category, ?string $itemCode): ?InsuranceCoverageRule
    {
        // First try item-specific rule from cache
        if ($itemCode && $this->coverageRulesCache !== null) {
            $cacheKey = $category.':'.$itemCode;
            $rule = $this->coverageRulesCache->get($cacheKey);
            if ($rule) {
                return $rule;
            }
        }

        // Fall back to category default from cache
        if ($this->categoryDefaultsCache !== null) {
            return $this->categoryDefaultsCache->get($category);
        }

        return null;
    }

    /**
     * Build a pricing item array with insurance data.
     * Legacy method kept for backward compatibility with other methods.
     */
    protected function buildPricingItem(
        int $id,
        string $type,
        ?string $code,
        string $name,
        string $category,
        ?float $cashPrice,
        ?int $insurancePlanId,
        bool $isNhis,
        int $itemId,
        string $itemType
    ): array {
        $insuranceTariff = null;
        $copayAmount = null;
        $coverageValue = null;
        $coverageType = null;
        $isMapped = true;
        $isUnmapped = false;
        $nhisCode = null;
        $coverageRuleId = null;

        if ($insurancePlanId) {
            // Get NHIS mapping if applicable
            if ($isNhis) {
                $mapping = NhisItemMapping::forItem($itemType, $itemId)->with('nhisTariff')->first();
                $isMapped = $mapping && $mapping->nhisTariff;

                if ($isMapped) {
                    $insuranceTariff = (float) $mapping->nhisTariff->price;
                    $nhisCode = $mapping->nhisTariff->nhis_code;
                }
            }

            // Get coverage rule
            $coverageCategory = $this->mapTypeToCoverageCategory($type);
            $rule = $this->getCoverageRuleForItem($insurancePlanId, $coverageCategory, $code);

            if ($rule) {
                $coverageRuleId = $rule->id;
                $copayAmount = $rule->patient_copay_amount ? (float) $rule->patient_copay_amount : null;
                $isUnmapped = (bool) $rule->is_unmapped;

                if (! $isNhis) {
                    $insuranceTariff = $rule->tariff_amount ? (float) $rule->tariff_amount : null;
                    $coverageValue = $rule->coverage_value ? (float) $rule->coverage_value : null;
                    $coverageType = $rule->coverage_type;
                }
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'cash_price' => $cashPrice,
            'insurance_tariff' => $insuranceTariff,
            'copay_amount' => $copayAmount,
            'coverage_value' => $coverageValue,
            'coverage_type' => $coverageType,
            'is_mapped' => $isMapped,
            'is_unmapped' => $isUnmapped,
            'nhis_code' => $nhisCode,
            'coverage_rule_id' => $coverageRuleId,
        ];
    }

    /**
     * Map item type to coverage category.
     */
    protected function mapTypeToCoverageCategory(string $type): string
    {
        return match ($type) {
            'drug' => 'drug',
            'lab' => 'lab',
            'consultation' => 'consultation',
            'procedure' => 'procedure',
            default => $type,
        };
    }

    /**
     * Get coverage rule for a specific item.
     */
    protected function getCoverageRuleForItem(int $insurancePlanId, string $category, ?string $itemCode): ?InsuranceCoverageRule
    {
        // First try item-specific rule
        if ($itemCode) {
            $specificRule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $category)
                ->where('item_code', $itemCode)
                ->where('is_active', true)
                ->first();

            if ($specificRule) {
                return $specificRule;
            }
        }

        // Fall back to general category rule
        return InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
            ->where('coverage_category', $category)
            ->whereNull('item_code')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Update cash price for an item.
     *
     * @throws \InvalidArgumentException
     */
    public function updateCashPrice(
        string $itemType,
        int $itemId,
        float $price
    ): bool {
        $oldValue = null;
        $itemCode = null;

        DB::beginTransaction();

        try {
            switch ($itemType) {
                case 'drug':
                    $drug = Drug::findOrFail($itemId);
                    $oldValue = (float) $drug->unit_price;
                    $itemCode = $drug->drug_code;
                    $drug->update(['unit_price' => $price]);
                    break;

                case 'lab':
                    $labService = LabService::findOrFail($itemId);
                    $oldValue = (float) $labService->price;
                    $itemCode = $labService->code;
                    $labService->update(['price' => $price]);
                    break;

                case 'consultation':
                    // itemId is now department_id, find or create billing record
                    $department = Department::findOrFail($itemId);
                    $billing = $department->billing;

                    if ($billing) {
                        $oldValue = (float) $billing->consultation_fee;
                        $billing->update(['consultation_fee' => $price]);
                    } else {
                        $oldValue = null;
                        $billing = DepartmentBilling::create([
                            'department_id' => $department->id,
                            'department_code' => $department->code,
                            'department_name' => $department->name,
                            'consultation_fee' => $price,
                            'is_active' => true,
                        ]);
                    }
                    $itemCode = $department->code;
                    break;

                case 'procedure':
                    $procedure = MinorProcedureType::findOrFail($itemId);
                    $oldValue = (float) $procedure->price;
                    $itemCode = $procedure->code;
                    $procedure->update(['price' => $price]);
                    break;

                default:
                    throw new \InvalidArgumentException("Invalid item type: {$itemType}");
            }

            // Log the change
            PricingChangeLog::logChange(
                $itemType,
                $itemId,
                $itemCode,
                PricingChangeLog::FIELD_CASH_PRICE,
                null,
                $oldValue,
                $price,
                auth()->id() ?? 0
            );

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update insurance copay for an item.
     * Creates InsuranceCoverageRule if not exists.
     */
    public function updateInsuranceCopay(
        int $insurancePlanId,
        string $itemType,
        int $itemId,
        string $itemCode,
        float $copayAmount
    ): InsuranceCoverageRule {
        $coverageCategory = $this->mapTypeToCoverageCategory($itemType);

        DB::beginTransaction();

        try {
            // Find or create item-specific coverage rule
            $rule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $coverageCategory)
                ->where('item_code', $itemCode)
                ->first();

            $oldValue = $rule ? (float) $rule->patient_copay_amount : null;

            if ($rule) {
                $rule->update(['patient_copay_amount' => $copayAmount]);
            } else {
                // Get item description
                $itemDescription = $this->getItemDescription($itemType, $itemId);

                $rule = InsuranceCoverageRule::create([
                    'insurance_plan_id' => $insurancePlanId,
                    'coverage_category' => $coverageCategory,
                    'item_code' => $itemCode,
                    'item_description' => $itemDescription,
                    'is_covered' => true,
                    'coverage_type' => 'full',
                    'coverage_value' => 100,
                    'patient_copay_amount' => $copayAmount,
                    'is_active' => true,
                ]);
            }

            // Log the change
            PricingChangeLog::logChange(
                $itemType,
                $itemId,
                $itemCode,
                PricingChangeLog::FIELD_COPAY,
                $insurancePlanId,
                $oldValue,
                $copayAmount,
                auth()->id() ?? 0
            );

            DB::commit();

            return $rule;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get item description for coverage rule.
     */
    protected function getItemDescription(string $itemType, int $itemId): string
    {
        return match ($itemType) {
            'drug' => Drug::find($itemId)?->name ?? 'Unknown Drug',
            'lab' => LabService::find($itemId)?->name ?? 'Unknown Lab Service',
            'consultation' => Department::find($itemId)?->name ?? 'Unknown Department',
            'procedure' => MinorProcedureType::find($itemId)?->name ?? 'Unknown Procedure',
            default => 'Unknown Item',
        };
    }

    /**
     * Update insurance coverage settings for an item.
     *
     * @param  array{tariff_amount?: float, coverage_value?: float, coverage_type?: string, patient_copay_amount?: float}  $coverageData
     */
    public function updateInsuranceCoverage(
        int $insurancePlanId,
        string $itemType,
        int $itemId,
        string $itemCode,
        array $coverageData
    ): InsuranceCoverageRule {
        $coverageCategory = $this->mapTypeToCoverageCategory($itemType);

        DB::beginTransaction();

        try {
            // Find or create item-specific coverage rule
            $rule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $coverageCategory)
                ->where('item_code', $itemCode)
                ->first();

            $oldTariff = $rule ? (float) $rule->tariff_amount : null;
            $oldCoverage = $rule ? (float) $rule->coverage_value : null;

            if ($rule) {
                $rule->update($coverageData);
            } else {
                // Get item description
                $itemDescription = $this->getItemDescription($itemType, $itemId);

                $rule = InsuranceCoverageRule::create(array_merge([
                    'insurance_plan_id' => $insurancePlanId,
                    'coverage_category' => $coverageCategory,
                    'item_code' => $itemCode,
                    'item_description' => $itemDescription,
                    'is_covered' => true,
                    'is_active' => true,
                ], $coverageData));
            }

            // Log tariff change if applicable
            if (isset($coverageData['tariff_amount'])) {
                PricingChangeLog::logChange(
                    $itemType,
                    $itemId,
                    $itemCode,
                    PricingChangeLog::FIELD_TARIFF,
                    $insurancePlanId,
                    $oldTariff,
                    $coverageData['tariff_amount'],
                    auth()->id() ?? 0
                );
            }

            // Log coverage change if applicable
            if (isset($coverageData['coverage_value'])) {
                PricingChangeLog::logChange(
                    $itemType,
                    $itemId,
                    $itemCode,
                    PricingChangeLog::FIELD_COVERAGE,
                    $insurancePlanId,
                    $oldCoverage,
                    $coverageData['coverage_value'],
                    auth()->id() ?? 0
                );
            }

            // Log copay change if applicable
            if (isset($coverageData['patient_copay_amount'])) {
                $oldCopay = $rule->getOriginal('patient_copay_amount');
                PricingChangeLog::logChange(
                    $itemType,
                    $itemId,
                    $itemCode,
                    PricingChangeLog::FIELD_COPAY,
                    $insurancePlanId,
                    $oldCopay ? (float) $oldCopay : null,
                    $coverageData['patient_copay_amount'],
                    auth()->id() ?? 0
                );
            }

            DB::commit();

            return $rule;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Bulk update copay for multiple items.
     * Handles both mapped and unmapped items for NHIS plans.
     *
     * @param  array<array{type: string, id: int, code: string, is_mapped?: bool}>  $items
     * @return array{updated: int, errors: array}
     */
    public function bulkUpdateCopay(
        int $insurancePlanId,
        array $items,
        float $copayAmount
    ): array {
        $updated = 0;
        $errors = [];

        // Check if this is an NHIS plan
        $plan = InsurancePlan::with('provider')->find($insurancePlanId);
        $isNhis = $plan && $plan->provider && $plan->provider->is_nhis;

        foreach ($items as $item) {
            try {
                // For NHIS plans, check if item is mapped
                if ($isNhis) {
                    $isMapped = $item['is_mapped'] ?? $this->isItemMappedToNhis($item['type'], $item['id']);

                    if ($isMapped) {
                        // Use regular copay update for mapped items
                        $this->updateInsuranceCopay(
                            $insurancePlanId,
                            $item['type'],
                            $item['id'],
                            $item['code'],
                            $copayAmount
                        );
                    } else {
                        // Use flexible copay for unmapped items
                        $this->updateFlexibleCopay(
                            $insurancePlanId,
                            $item['type'],
                            $item['id'],
                            $item['code'],
                            $copayAmount
                        );
                    }
                } else {
                    // For non-NHIS plans, use regular copay update
                    $this->updateInsuranceCopay(
                        $insurancePlanId,
                        $item['type'],
                        $item['id'],
                        $item['code'],
                        $copayAmount
                    );
                }
                $updated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'item' => $item,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Check if an item is mapped to NHIS tariff.
     */
    protected function isItemMappedToNhis(string $itemType, int $itemId): bool
    {
        $nhisItemType = $this->mapTypeToNhisItemType($itemType);
        $mapping = NhisItemMapping::forItem($nhisItemType, $itemId)->with('nhisTariff')->first();

        return $mapping && $mapping->nhisTariff;
    }

    /**
     * Export pricing data to CSV.
     */
    public function exportToCsv(
        ?int $insurancePlanId,
        ?string $category = null,
        ?string $search = null,
        ?string $pricingStatus = null
    ): string {
        $data = $this->getPricingData($insurancePlanId, $category, $search, false, 10000, $pricingStatus);
        $items = $data['items']->items();
        $isNhis = $data['is_nhis'];

        $csv = fopen('php://temp', 'r+');

        // Write headers
        $headers = ['Code', 'Name', 'Category', 'Cash Price'];
        if ($insurancePlanId) {
            if ($isNhis) {
                $headers = array_merge($headers, ['NHIS Code', 'NHIS Tariff', 'Patient Copay', 'Is Mapped']);
            } else {
                $headers = array_merge($headers, ['Insurance Tariff', 'Coverage Type', 'Coverage Value', 'Patient Copay']);
            }
        }
        fputcsv($csv, $headers);

        // Write data rows
        foreach ($items as $item) {
            $row = [
                $item['code'],
                $item['name'],
                $item['category'],
                $item['cash_price'],
            ];

            if ($insurancePlanId) {
                if ($isNhis) {
                    $row = array_merge($row, [
                        $item['nhis_code'] ?? '',
                        $item['insurance_tariff'] ?? '',
                        $item['copay_amount'] ?? '',
                        $item['is_mapped'] ? 'Yes' : 'No',
                    ]);
                } else {
                    $row = array_merge($row, [
                        $item['insurance_tariff'] ?? '',
                        $item['coverage_type'] ?? '',
                        $item['coverage_value'] ?? '',
                        $item['copay_amount'] ?? '',
                    ]);
                }
            }

            fputcsv($csv, $row);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    /**
     * Import pricing data from CSV/Excel.
     *
     * @return array{imported: int, updated: int, skipped: int, errors: array}
     */
    public function importFromFile(
        UploadedFile $file,
        ?int $insurancePlanId = null
    ): array {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $handle = fopen($file->getPathname(), 'r');
        $headers = fgetcsv($handle);

        // Normalize headers
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

        $codeIndex = array_search('code', $headers);
        $cashPriceIndex = array_search('cash price', $headers);
        $copayIndex = array_search('patient copay', $headers);

        if ($codeIndex === false) {
            fclose($handle);

            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [['row' => 0, 'error' => 'Missing required "Code" column']],
            ];
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            try {
                $code = trim($row[$codeIndex] ?? '');

                if (empty($code)) {
                    $skipped++;

                    continue;
                }

                // Find the item by code
                $item = $this->findItemByCode($code);

                if (! $item) {
                    $errors[] = ['row' => $rowNumber, 'error' => "Item with code '{$code}' not found"];
                    $skipped++;

                    continue;
                }

                $wasUpdated = false;

                // Update cash price if present
                if ($cashPriceIndex !== false && isset($row[$cashPriceIndex]) && $row[$cashPriceIndex] !== '') {
                    $cashPrice = (float) $row[$cashPriceIndex];
                    if ($cashPrice > 0) {
                        $this->updateCashPrice($item['type'], $item['id'], $cashPrice);
                        $wasUpdated = true;
                    }
                }

                // Update copay if present and insurance plan selected
                if ($insurancePlanId && $copayIndex !== false && isset($row[$copayIndex]) && $row[$copayIndex] !== '') {
                    $copay = (float) $row[$copayIndex];
                    if ($copay >= 0) {
                        $this->updateInsuranceCopay($insurancePlanId, $item['type'], $item['id'], $code, $copay);
                        $wasUpdated = true;
                    }
                }

                if ($wasUpdated) {
                    $updated++;
                } else {
                    $skipped++;
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNumber, 'error' => $e->getMessage()];
                $skipped++;
            }
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Find an item by its code.
     *
     * @return array{type: string, id: int}|null
     */
    protected function findItemByCode(string $code): ?array
    {
        // Check drugs
        $drug = Drug::where('drug_code', $code)->first();
        if ($drug) {
            return ['type' => 'drug', 'id' => $drug->id];
        }

        // Check lab services
        $labService = LabService::where('code', $code)->first();
        if ($labService) {
            return ['type' => 'lab', 'id' => $labService->id];
        }

        // Check departments (for consultation pricing)
        $department = Department::where('code', $code)->first();
        if ($department) {
            return ['type' => 'consultation', 'id' => $department->id];
        }

        // Check procedures
        $procedure = MinorProcedureType::where('code', $code)->first();
        if ($procedure) {
            return ['type' => 'procedure', 'id' => $procedure->id];
        }

        return null;
    }

    /**
     * Update flexible copay for an unmapped NHIS item.
     * Creates InsuranceCoverageRule with is_unmapped = true.
     *
     * @param  int  $nhisplanId  The NHIS insurance plan ID
     * @param  string  $itemType  The type of item (drug, lab, consultation, procedure)
     * @param  int  $itemId  The ID of the item
     * @param  string  $itemCode  The code of the item
     * @param  float|null  $copayAmount  The copay amount (null to clear)
     * @return InsuranceCoverageRule|null The created/updated rule, or null if cleared
     */
    public function updateFlexibleCopay(
        int $nhisplanId,
        string $itemType,
        int $itemId,
        string $itemCode,
        ?float $copayAmount
    ): ?InsuranceCoverageRule {
        $coverageCategory = $this->mapTypeToCoverageCategory($itemType);

        DB::beginTransaction();

        try {
            // Find existing unmapped coverage rule for this item
            $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisplanId)
                ->where('coverage_category', $coverageCategory)
                ->where('item_code', $itemCode)
                ->where('is_unmapped', true)
                ->first();

            $oldValue = $rule ? (float) $rule->patient_copay_amount : null;

            // If copay is null or clearing, delete or nullify the rule
            if ($copayAmount === null) {
                if ($rule) {
                    // Log the change before deleting (use 0 to indicate cleared since DB doesn't allow null)
                    PricingChangeLog::logChange(
                        $itemType,
                        $itemId,
                        $itemCode,
                        PricingChangeLog::FIELD_COPAY,
                        $nhisplanId,
                        $oldValue,
                        0, // 0 indicates copay was cleared/removed
                        auth()->id() ?? 0
                    );

                    $rule->delete();
                }

                DB::commit();

                return null;
            }

            // Create or update the rule with is_unmapped = true
            if ($rule) {
                $rule->update(['patient_copay_amount' => $copayAmount]);
            } else {
                // Get item description
                $itemDescription = $this->getItemDescription($itemType, $itemId);

                $rule = InsuranceCoverageRule::create([
                    'insurance_plan_id' => $nhisplanId,
                    'coverage_category' => $coverageCategory,
                    'item_code' => $itemCode,
                    'item_description' => $itemDescription,
                    'is_covered' => true,
                    'coverage_type' => 'full',
                    'coverage_value' => 0, // No insurance coverage for unmapped items
                    'patient_copay_amount' => $copayAmount,
                    'is_unmapped' => true,
                    'is_active' => true,
                ]);
            }

            // Log the change
            PricingChangeLog::logChange(
                $itemType,
                $itemId,
                $itemCode,
                PricingChangeLog::FIELD_COPAY,
                $nhisplanId,
                $oldValue,
                $copayAmount,
                auth()->id() ?? 0
            );

            DB::commit();

            return $rule;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Determine pricing status for an item.
     *
     * @param  string  $itemType  The type of item (drug, lab, consultation, procedure)
     * @param  int  $itemId  The ID of the item
     * @param  int|null  $insurancePlanId  The insurance plan ID (optional)
     * @return string 'priced'|'unpriced'|'nhis_mapped'|'flexible_copay'|'not_mapped'
     */
    public function getPricingStatus(
        string $itemType,
        int $itemId,
        ?int $insurancePlanId = null
    ): string {
        // Get the item and its cash price
        $cashPrice = $this->getItemCashPrice($itemType, $itemId);
        $itemCode = $this->getItemCode($itemType, $itemId);

        // If no cash price, it's unpriced
        if ($cashPrice === null || $cashPrice <= 0) {
            return 'unpriced';
        }

        // If no insurance plan selected, just check if priced
        if (! $insurancePlanId) {
            return 'priced';
        }

        // Check if this is an NHIS plan
        $plan = InsurancePlan::with('provider')->find($insurancePlanId);
        $isNhis = $plan && $plan->provider && $plan->provider->is_nhis;

        if ($isNhis) {
            // Check if item is mapped to NHIS tariff
            $nhisItemType = $this->mapTypeToNhisItemType($itemType);
            $mapping = NhisItemMapping::forItem($nhisItemType, $itemId)->with('nhisTariff')->first();
            $isMapped = $mapping && $mapping->nhisTariff;

            if ($isMapped) {
                return 'nhis_mapped';
            }

            // Check for flexible copay rule (unmapped with copay)
            $coverageCategory = $this->mapTypeToCoverageCategory($itemType);
            $flexibleCopayRule = InsuranceCoverageRule::where('insurance_plan_id', $insurancePlanId)
                ->where('coverage_category', $coverageCategory)
                ->where('item_code', $itemCode)
                ->where('is_unmapped', true)
                ->where('is_active', true)
                ->whereNotNull('patient_copay_amount')
                ->first();

            if ($flexibleCopayRule) {
                return 'flexible_copay';
            }

            return 'not_mapped';
        }

        // For non-NHIS plans, just return priced
        return 'priced';
    }

    /**
     * Get cash price for an item.
     */
    protected function getItemCashPrice(string $itemType, int $itemId): ?float
    {
        return match ($itemType) {
            'drug' => Drug::find($itemId)?->unit_price,
            'lab' => LabService::find($itemId)?->price,
            'consultation' => Department::find($itemId)?->billing?->consultation_fee,
            'procedure' => MinorProcedureType::find($itemId)?->price,
            default => null,
        };
    }

    /**
     * Get item code for an item.
     */
    protected function getItemCode(string $itemType, int $itemId): ?string
    {
        return match ($itemType) {
            'drug' => Drug::find($itemId)?->drug_code,
            'lab' => LabService::find($itemId)?->code,
            'consultation' => Department::find($itemId)?->code,
            'procedure' => MinorProcedureType::find($itemId)?->code,
            default => null,
        };
    }

    /**
     * Map item type to NHIS item type for mapping lookup.
     */
    protected function mapTypeToNhisItemType(string $itemType): string
    {
        return match ($itemType) {
            'drug' => 'drug',
            'lab' => 'lab_service',
            'consultation' => 'consultation',
            'procedure' => 'procedure',
            default => $itemType,
        };
    }

    /**
     * Generate import template CSV.
     */
    public function generateImportTemplate(
        ?int $insurancePlanId = null,
        ?string $category = null,
        ?string $pricingStatus = null
    ): string {
        $data = $this->getPricingData($insurancePlanId, $category, null, false, 10000, $pricingStatus);
        $items = $data['items']->items();

        $csv = fopen('php://temp', 'r+');

        // Write headers
        $headers = ['Code', 'Name', 'Category', 'Cash Price'];
        if ($insurancePlanId) {
            $headers[] = 'Patient Copay';
        }
        fputcsv($csv, $headers);

        // Write current data as reference
        foreach ($items as $item) {
            $row = [
                $item['code'],
                $item['name'],
                $item['category'],
                $item['cash_price'],
            ];

            if ($insurancePlanId) {
                $row[] = $item['copay_amount'] ?? '';
            }

            fputcsv($csv, $row);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    /**
     * Get pricing status summary counts.
     *
     * Returns counts of items in each pricing status category:
     * - unpriced: Items with null or zero cash price
     * - priced: Items with positive cash price
     * - nhis_mapped: Items mapped to NHIS tariffs (when NHIS plan selected)
     * - nhis_unmapped: Items not mapped to NHIS tariffs (when NHIS plan selected)
     * - flexible_copay: Unmapped items with flexible copay configured (when NHIS plan selected)
     *
     * @param  int|null  $insurancePlanId  The insurance plan ID (optional)
     * @return array{
     *     unpriced: int,
     *     priced: int,
     *     nhis_mapped: int,
     *     nhis_unmapped: int,
     *     flexible_copay: int
     * }
     */
    public function getPricingStatusSummary(?int $insurancePlanId = null): array
    {
        // Get all items without pagination
        $data = $this->getPricingData($insurancePlanId, null, null, false, 100000);
        $items = collect($data['items']->items());
        $isNhis = $data['is_nhis'];

        // Count unpriced items (null or zero cash price)
        $unpriced = $items->filter(fn ($item) => $item['cash_price'] === null || $item['cash_price'] <= 0)->count();

        // Count priced items (positive cash price)
        $priced = $items->filter(fn ($item) => $item['cash_price'] !== null && $item['cash_price'] > 0)->count();

        // Initialize NHIS-specific counts
        $nhisMapped = 0;
        $nhisUnmapped = 0;
        $flexibleCopay = 0;

        if ($isNhis && $insurancePlanId) {
            // For NHIS plans, calculate mapping status
            foreach ($items as $item) {
                $status = $this->getPricingStatus($item['type'], $item['id'], $insurancePlanId);

                if ($status === 'nhis_mapped') {
                    $nhisMapped++;
                } elseif ($status === 'flexible_copay') {
                    $flexibleCopay++;
                } elseif ($status === 'not_mapped') {
                    $nhisUnmapped++;
                }
            }
        }

        return [
            'unpriced' => $unpriced,
            'priced' => $priced,
            'nhis_mapped' => $nhisMapped,
            'nhis_unmapped' => $nhisUnmapped,
            'flexible_copay' => $flexibleCopay,
        ];
    }
}
