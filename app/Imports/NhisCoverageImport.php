<?php

namespace App\Imports;

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\LabService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * NHIS Coverage Import
 *
 * This import class processes NHIS coverage CSV files and saves ONLY the copay_amount
 * to coverage rules. Tariff values in the CSV are ignored since they come from the
 * NHIS Tariff Master.
 *
 * Requirements: 6.3, 6.4
 */
class NhisCoverageImport implements ToArray, WithHeadingRow
{
    private array $results = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    public function __construct(
        private readonly InsurancePlan $plan,
        private readonly string $category
    ) {}

    public function array(array $rows): array
    {
        return $rows;
    }

    /**
     * Process the imported data and save only copay amounts.
     *
     * @param  array  $rows  The imported rows from the CSV
     * @return array{created: int, updated: int, skipped: int, errors: array}
     */
    public function processRows(array $rows): array
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 for header row and 0-index

                try {
                    $this->processRow($row, $rowNumber);
                } catch (\Exception $e) {
                    $this->results['skipped']++;
                    $this->results['errors'][] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->results['errors'][] = [
                'row' => 0,
                'error' => 'Import failed: '.$e->getMessage(),
            ];
        }

        return $this->results;
    }

    /**
     * Process a single row from the import.
     */
    private function processRow(array $row, int $rowNumber): void
    {
        // Validate required fields
        $itemCode = trim($row['item_code'] ?? '');

        if (empty($itemCode)) {
            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'error' => 'Missing item_code',
            ];

            return;
        }

        // Validate item exists in system
        if (! $this->itemExists($itemCode)) {
            $this->results['skipped']++;
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'error' => sprintf('Item code %s not found in system', $itemCode),
            ];

            return;
        }

        // Get copay amount - this is the ONLY value we save from the CSV
        // Tariff values are IGNORED as per Requirements 6.3, 6.4
        $copayAmount = $this->parseCopayAmount($row['copay_amount'] ?? '');

        // Skip rows with no copay (they should use the general rule)
        if ($copayAmount === null || $copayAmount === 0.0) {
            // Check if there's an existing rule to delete
            $existingRule = InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
                ->where('coverage_category', $this->category)
                ->where('item_code', $itemCode)
                ->first();

            if ($existingRule) {
                // If copay is empty/0, we could optionally delete the rule
                // For now, we'll update it to 0 copay
                $existingRule->update([
                    'patient_copay_amount' => 0,
                ]);
                $this->results['updated']++;
            } else {
                $this->results['skipped']++;
            }

            return;
        }

        // Create or update coverage rule with ONLY copay amount
        // Note: We do NOT save tariff_amount from CSV - it comes from NHIS Tariff Master
        $rule = InsuranceCoverageRule::updateOrCreate(
            [
                'insurance_plan_id' => $this->plan->id,
                'coverage_category' => $this->category,
                'item_code' => $itemCode,
            ],
            [
                'item_description' => $row['item_name'] ?? null,
                // For NHIS, coverage is always 100% of the NHIS tariff price
                'coverage_type' => 'full',
                'coverage_value' => 100,
                // Only save copay amount - tariff comes from Master
                'patient_copay_amount' => $copayAmount,
                'patient_copay_percentage' => 0,
                'is_covered' => true,
                'is_active' => true,
                'effective_from' => now(),
                // Note: tariff_amount is NOT set here - it's looked up from NHIS Tariff Master
            ]
        );

        $rule->wasRecentlyCreated ? $this->results['created']++ : $this->results['updated']++;
    }

    /**
     * Parse copay amount from string value.
     */
    private function parseCopayAmount(string $value): ?float
    {
        $value = trim($value);

        if ($value === '' || strtoupper($value) === 'NOT MAPPED') {
            return null;
        }

        // Remove any currency symbols and commas
        $value = preg_replace('/[^0-9.]/', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Check if an item exists in the system.
     */
    private function itemExists(string $itemCode): bool
    {
        return match ($this->category) {
            'drug' => Drug::where('drug_code', $itemCode)->exists(),
            'lab' => LabService::where('code', $itemCode)->exists(),
            // For other categories, assume they exist
            default => true,
        };
    }

    /**
     * Get the import results.
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
