<?php

namespace App\Services;

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
    public function __construct(
        protected InsuranceCoverageService $coverageService
    ) {}

    /**
     * Get all pricing data for a specific insurance plan.
     *
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
        ?int $perPage = null
    ): array {
        // Default to 5 items per page, allow override via request
        $perPage = $perPage ?? (int) request()->get('per_page', 5);
        $plan = $insurancePlanId ? InsurancePlan::with('provider')->find($insurancePlanId) : null;
        $isNhis = $plan && $plan->provider && $plan->provider->is_nhis;

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

        // Filter unmapped items for NHIS
        if ($unmappedOnly && $isNhis) {
            $items = $items->filter(fn ($item) => ! $item['is_mapped']);
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
            return $this->buildPricingItem(
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
            return $this->buildPricingItem(
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
     * Get consultation pricing items (from department billing).
     */
    protected function getConsultationPricingItems(?int $insurancePlanId, bool $isNhis, ?string $search): Collection
    {
        $query = DepartmentBilling::query()->active();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('department_name', 'LIKE', "%{$search}%")
                    ->orWhere('department_code', 'LIKE', "%{$search}%");
            });
        }

        $billings = $query->get();

        return $billings->map(function ($billing) use ($insurancePlanId, $isNhis) {
            return $this->buildPricingItem(
                $billing->id,
                'consultation',
                $billing->department_code,
                $billing->department_name.' Consultation',
                'consultation',
                (float) $billing->consultation_fee,
                $insurancePlanId,
                $isNhis,
                $billing->id,
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
            return $this->buildPricingItem(
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
     * Build a pricing item array with insurance data.
     */
    protected function buildPricingItem(
        int $id,
        string $type,
        ?string $code,
        string $name,
        string $category,
        float $cashPrice,
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
                    $billing = DepartmentBilling::findOrFail($itemId);
                    $oldValue = (float) $billing->consultation_fee;
                    $itemCode = $billing->department_code;
                    $billing->update(['consultation_fee' => $price]);
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
            'consultation' => DepartmentBilling::find($itemId)?->department_name ?? 'Unknown Department',
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
     *
     * @param  array<array{type: string, id: int, code: string}>  $items
     * @return array{updated: int, errors: array}
     */
    public function bulkUpdateCopay(
        int $insurancePlanId,
        array $items,
        float $copayAmount
    ): array {
        $updated = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $this->updateInsuranceCopay(
                    $insurancePlanId,
                    $item['type'],
                    $item['id'],
                    $item['code'],
                    $copayAmount
                );
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
     * Export pricing data to CSV.
     */
    public function exportToCsv(
        ?int $insurancePlanId,
        ?string $category = null,
        ?string $search = null
    ): string {
        $data = $this->getPricingData($insurancePlanId, $category, $search, false, 10000);
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

        // Check department billing
        $billing = DepartmentBilling::where('department_code', $code)->first();
        if ($billing) {
            return ['type' => 'consultation', 'id' => $billing->id];
        }

        // Check procedures
        $procedure = MinorProcedureType::where('code', $code)->first();
        if ($procedure) {
            return ['type' => 'procedure', 'id' => $procedure->id];
        }

        return null;
    }

    /**
     * Generate import template CSV.
     */
    public function generateImportTemplate(
        ?int $insurancePlanId = null,
        ?string $category = null
    ): string {
        $data = $this->getPricingData($insurancePlanId, $category, null, false, 10000);
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
}
