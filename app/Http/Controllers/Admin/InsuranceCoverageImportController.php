<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CoverageExceptionTemplate;
use App\Exports\NhisCoverageTemplate;
use App\Http\Controllers\Controller;
use App\Imports\CoverageExceptionImport;
use App\Imports\NhisCoverageImport;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\LabService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InsuranceCoverageImportController extends Controller
{
    public function downloadTemplate(InsurancePlan $plan, string $category)
    {
        $this->authorize('manage', $plan);

        // Validate category
        $validCategories = ['drug', 'lab', 'consultation', 'procedure', 'ward', 'nursing'];
        if (! in_array($category, $validCategories)) {
            return response()->json(['error' => 'Invalid category'], 400);
        }

        $fileName = sprintf(
            'coverage_template_%s_%s_%s.xlsx',
            $category,
            str_replace(' ', '_', $plan->name),
            now()->format('Y-m-d')
        );

        return Excel::download(
            new CoverageExceptionTemplate($category, $plan->id),
            $fileName
        );
    }

    public function preview(Request $request, InsurancePlan $plan)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'category' => 'required|in:consultation,drug,lab,procedure,ward,nursing',
        ]);

        $file = $request->file('file');
        $category = $request->input('category');

        try {
            // Parse file - get the Data sheet (index 1)
            $rows = Excel::toArray(new CoverageExceptionImport, $file)[1] ?? [];

            // Validate each row
            $validated = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $validation = $this->validateRow($row, $category, $plan);

                if ($validation['valid']) {
                    $validated[] = $validation['data'];
                } else {
                    $errors[] = [
                        'row' => $index + 2, // +2 because of header row and 0-index
                        'data' => $row,
                        'errors' => $validation['errors'],
                    ];
                }
            }

            return response()->json([
                'valid_rows' => $validated,
                'errors' => $errors,
                'summary' => [
                    'total' => count($rows),
                    'valid' => count($validated),
                    'invalid' => count($errors),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to parse file: '.$e->getMessage(),
            ], 422);
        }
    }

    public function import(Request $request, InsurancePlan $plan)
    {
        $this->authorize('manage', $plan);

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'category' => 'required|in:drug,lab,consultation,procedure,ward,nursing',
        ]);

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        try {
            // Read from the Data sheet (index 1), not Instructions sheet (index 0)
            $sheets = Excel::toArray([], $request->file('file'));
            $rows = $sheets[1] ?? $sheets[0]; // Try Data sheet first, fallback to first sheet

            // Skip header row
            $header = array_shift($rows);

            // Detect format (old vs new)
            $isNewFormat = in_array('coverage_type', $header);

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we skipped header and Excel is 1-indexed

                try {
                    $rowData = array_combine($header, $row);

                    // Convert old format to new format if needed
                    if (! $isNewFormat && isset($rowData['coverage_percentage'])) {
                        $rowData['coverage_type'] = 'percentage';
                        $rowData['coverage_value'] = $rowData['coverage_percentage'];
                    }

                    // Validate required fields
                    if (empty($rowData['item_code'])) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => 'Missing item_code',
                        ];

                        continue;
                    }

                    // Validate coverage type
                    $validTypes = ['percentage', 'fixed_amount', 'full', 'excluded'];
                    if (! isset($rowData['coverage_type']) || ! in_array($rowData['coverage_type'], $validTypes)) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => sprintf(
                                'Invalid coverage_type: %s. Must be: %s',
                                $rowData['coverage_type'] ?? 'null',
                                implode(', ', $validTypes)
                            ),
                        ];

                        continue;
                    }

                    // Validate item exists in system
                    if (! $this->itemExists($rowData['item_code'], $request->category)) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'row' => $rowNumber,
                            'error' => sprintf('Item code %s not found in system', $rowData['item_code']),
                        ];

                        continue;
                    }

                    // Process coverage based on type
                    $coverageData = $this->processCoverageType(
                        $rowData['coverage_type'],
                        (float) ($rowData['coverage_value'] ?? 0)
                    );

                    // Map coverage_type to database enum value
                    $dbCoverageType = $rowData['coverage_type'] === 'fixed_amount' ? 'fixed' : $rowData['coverage_type'];

                    // Create or update rule
                    $rule = InsuranceCoverageRule::updateOrCreate(
                        [
                            'insurance_plan_id' => $plan->id,
                            'coverage_category' => $request->category,
                            'item_code' => $rowData['item_code'],
                        ],
                        [
                            'item_description' => $rowData['item_name'] ?? null,
                            'coverage_type' => $dbCoverageType,
                            'coverage_value' => $coverageData['coverage_value'],
                            'tariff_amount' => isset($rowData['tariff_amount']) && $rowData['tariff_amount'] !== '' ? (float) $rowData['tariff_amount'] : null,
                            'patient_copay_percentage' => $coverageData['copay_percentage'],
                            'patient_copay_amount' => isset($rowData['patient_copay_amount']) && $rowData['patient_copay_amount'] !== '' ? (float) $rowData['patient_copay_amount'] : 0,
                            'is_covered' => $coverageData['is_covered'],
                            'is_active' => true,
                            'effective_from' => now(),
                            'notes' => $rowData['notes'] ?? null,
                        ]
                    );

                    $rule->wasRecentlyCreated ? $results['created']++ : $results['updated']++;
                } catch (\Exception $e) {
                    $results['skipped']++;
                    $results['errors'][] = [
                        'row' => $rowNumber,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    private function validateRow(array $row, string $category, InsurancePlan $plan): array
    {
        $errors = [];
        $data = [];

        // Validate item_code
        if (empty($row['item_code'])) {
            $errors[] = 'Item code is required';
        } else {
            $itemExists = $this->itemExists($row['item_code'], $category);
            if (! $itemExists) {
                $errors[] = "Item code '{$row['item_code']}' not found in {$category} category";
            }
            $data['item_code'] = $row['item_code'];
        }

        // Validate item_name
        $data['item_name'] = $row['item_name'] ?? '';

        // Validate coverage_percentage
        if (! isset($row['coverage_percentage']) || $row['coverage_percentage'] === '') {
            $errors[] = 'Coverage percentage is required';
        } elseif (! is_numeric($row['coverage_percentage'])) {
            $errors[] = 'Coverage percentage must be a number';
        } elseif ($row['coverage_percentage'] < 0 || $row['coverage_percentage'] > 100) {
            $errors[] = 'Coverage percentage must be between 0 and 100';
        } else {
            $data['coverage_percentage'] = (float) $row['coverage_percentage'];
        }

        // Notes are optional
        $data['notes'] = $row['notes'] ?? null;

        // Check for duplicate exception
        if (empty($errors) && isset($data['item_code'])) {
            $existingRule = InsuranceCoverageRule::where('insurance_plan_id', $plan->id)
                ->where('coverage_category', $category)
                ->where('item_code', $data['item_code'])
                ->first();

            if ($existingRule) {
                // Not an error, just a note that it will be updated
                $data['will_update'] = true;
            }
        }

        return [
            'valid' => empty($errors),
            'data' => $data,
            'errors' => $errors,
        ];
    }

    private function itemExists(string $itemCode, string $category): bool
    {
        return match ($category) {
            'drug' => Drug::where('drug_code', $itemCode)->exists(),
            'lab' => LabService::where('service_code', $itemCode)->exists(),
            // For other categories, we'll assume they exist for now
            // In a real implementation, you'd check the appropriate tables
            default => true,
        };
    }

    /**
     * Process coverage type and return standardized data
     */
    private function processCoverageType(string $type, float $value): array
    {
        return match ($type) {
            'percentage' => [
                'coverage_value' => $value,
                'copay_percentage' => 100 - $value,
                'is_covered' => true,
            ],
            'fixed_amount' => [
                'coverage_value' => $value,
                'copay_percentage' => 0, // Calculated dynamically based on item price
                'is_covered' => true,
            ],
            'full' => [
                'coverage_value' => 100,
                'copay_percentage' => 0,
                'is_covered' => true,
            ],
            'excluded' => [
                'coverage_value' => 0,
                'copay_percentage' => 100,
                'is_covered' => false,
            ],
        };
    }

    /**
     * Download NHIS coverage template with pre-filled NHIS tariff prices from Master.
     *
     * Requirements: 6.1, 6.2
     */
    public function downloadNhisTemplate(InsurancePlan $plan, string $category)
    {
        $this->authorize('manage', $plan);

        // Verify this is an NHIS plan
        if (! $plan->provider || ! $plan->provider->isNhis()) {
            return response()->json(['error' => 'This plan is not an NHIS plan'], 400);
        }

        // Validate category
        $validCategories = ['drug', 'lab', 'consultation', 'procedure', 'ward', 'nursing'];
        if (! in_array($category, $validCategories)) {
            return response()->json(['error' => 'Invalid category'], 400);
        }

        $fileName = sprintf(
            'nhis_coverage_template_%s_%s_%s.xlsx',
            $category,
            str_replace(' ', '_', $plan->plan_name),
            now()->format('Y-m-d')
        );

        return Excel::download(
            new NhisCoverageTemplate($category, $plan->id),
            $fileName
        );
    }

    /**
     * Import NHIS coverage CSV - saves ONLY copay amounts.
     * Tariff values in the CSV are ignored since they come from the NHIS Tariff Master.
     *
     * Requirements: 6.3, 6.4
     */
    public function importNhisCoverage(Request $request, InsurancePlan $plan)
    {
        $this->authorize('manage', $plan);

        // Verify this is an NHIS plan
        if (! $plan->provider || ! $plan->provider->isNhis()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not an NHIS plan',
            ], 400);
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'category' => 'required|in:drug,lab,consultation,procedure,ward,nursing',
        ]);

        try {
            // Read from the Data sheet (index 1), not Instructions sheet (index 0)
            $sheets = Excel::toArray([], $request->file('file'));
            $rows = $sheets[1] ?? $sheets[0]; // Try Data sheet first, fallback to first sheet

            // Skip header row
            $header = array_shift($rows);

            // Normalize header keys
            $header = array_map(function ($h) {
                return strtolower(trim(str_replace(' ', '_', $h)));
            }, $header);

            // Convert rows to associative arrays
            $dataRows = [];
            foreach ($rows as $row) {
                if (count($row) === count($header)) {
                    $dataRows[] = array_combine($header, $row);
                }
            }

            // Process using NhisCoverageImport
            $importer = new NhisCoverageImport($plan, $request->category);
            $results = $importer->processRows($dataRows);

            return response()->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: '.$e->getMessage(),
            ], 500);
        }
    }
}
