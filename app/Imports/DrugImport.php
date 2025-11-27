<?php

namespace App\Imports;

use App\Models\Drug;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Illuminate\Support\Facades\DB;

/**
 * Drug Import with optional NHIS auto-mapping.
 */
class DrugImport
{
    private array $results = [
        'created' => 0,
        'updated' => 0,
        'mapped' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    /**
     * Process imported rows.
     *
     * @return array{created: int, updated: int, mapped: int, skipped: int, errors: array}
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
     * Process a single row.
     */
    private function processRow(array $row, int $rowNumber): void
    {
        // Get values with flexible key matching
        $drugCode = $this->getValue($row, ['drug_code', 'code']);
        $name = $this->getValue($row, ['name', 'drug_name']);
        $unitPrice = $this->getValue($row, ['unit_price', 'hospital_price', 'price']);

        // Validate required fields
        if (empty($drugCode)) {
            throw new \Exception('Missing drug_code');
        }

        if (empty($name)) {
            throw new \Exception('Missing name');
        }

        // unit_price is optional - default to 0 if not provided (can be updated later)
        if (! empty($unitPrice) && ! is_numeric($unitPrice)) {
            throw new \Exception('Invalid unit_price (must be numeric)');
        }
        $unitPrice = ! empty($unitPrice) && is_numeric($unitPrice) ? (float) $unitPrice : 0;

        // Optional fields with defaults for required enum columns
        $genericName = $this->getValue($row, ['generic_name']) ?: null;
        $form = $this->getValue($row, ['form', 'dosage_form']) ?: 'other';
        $strength = $this->getValue($row, ['strength']) ?: null;
        $category = $this->getValue($row, ['category']) ?: 'other';
        $nhisCode = $this->getValue($row, ['nhis_code']) ?: null;
        $unitType = $this->getValue($row, ['unit_type', 'unit']) ?: 'piece';
        $bottleSize = $this->getValue($row, ['bottle_size', 'vial_size', 'container_size']) ?: null;
        $description = $this->getValue($row, ['description']) ?: null;
        $minStock = $this->getValue($row, ['min_stock', 'minimum_stock_level']);
        $maxStock = $this->getValue($row, ['max_stock', 'maximum_stock_level']);

        // Normalize form to valid enum value
        $form = $this->normalizeForm(strtolower(trim($form)));
        $category = $this->normalizeCategory(strtolower(trim($category)));
        $unitType = $this->normalizeUnitType(strtolower(trim($unitType)));

        // Create or update drug
        $drug = Drug::updateOrCreate(
            ['drug_code' => trim($drugCode)],
            [
                'name' => trim($name),
                'generic_name' => $genericName ? trim($genericName) : null,
                'form' => $form,
                'strength' => $strength ? trim($strength) : null,
                'category' => $category,
                'unit_price' => $unitPrice,
                'unit_type' => $unitType,
                'bottle_size' => $bottleSize && is_numeric($bottleSize) ? (int) $bottleSize : null,
                'description' => $description ? trim($description) : null,
                'minimum_stock_level' => $minStock && is_numeric($minStock) ? (int) $minStock : 10,
                'maximum_stock_level' => $maxStock && is_numeric($maxStock) ? (int) $maxStock : 1000,
                'is_active' => true,
            ]
        );

        $drug->wasRecentlyCreated ? $this->results['created']++ : $this->results['updated']++;

        // Auto-create NHIS mapping if nhis_code provided
        if ($nhisCode) {
            $this->createNhisMapping($drug, trim($nhisCode), $rowNumber);
        }
    }

    /**
     * Create NHIS mapping for the drug.
     */
    private function createNhisMapping(Drug $drug, string $nhisCode, int $rowNumber): void
    {
        $nhisTariff = NhisTariff::where('nhis_code', $nhisCode)->first();

        if (! $nhisTariff) {
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'error' => "NHIS code '{$nhisCode}' not found in tariff master (drug created without mapping)",
            ];

            return;
        }

        NhisItemMapping::updateOrCreate(
            [
                'item_type' => 'drug',
                'item_id' => $drug->id,
            ],
            [
                'item_code' => $drug->drug_code,
                'nhis_tariff_id' => $nhisTariff->id,
            ]
        );

        $this->results['mapped']++;
    }

    /**
     * Normalize form value to valid enum.
     */
    private function normalizeForm(string $form): string
    {
        $validForms = ['tablet', 'capsule', 'syrup', 'suspension', 'injection', 'drops', 'cream', 'ointment', 'inhaler', 'patch', 'other'];

        // Map common variations
        $mappings = [
            'tab' => 'tablet',
            'tabs' => 'tablet',
            'cap' => 'capsule',
            'caps' => 'capsule',
            'inj' => 'injection',
            'susp' => 'suspension',
            'syr' => 'syrup',
            'crm' => 'cream',
            'oint' => 'ointment',
        ];

        if (isset($mappings[$form])) {
            return $mappings[$form];
        }

        return in_array($form, $validForms) ? $form : 'other';
    }

    /**
     * Normalize category value to valid enum.
     */
    private function normalizeCategory(string $category): string
    {
        $validCategories = ['analgesics', 'antibiotics', 'antivirals', 'antifungals', 'cardiovascular', 'diabetes', 'respiratory', 'gastrointestinal', 'neurological', 'psychiatric', 'dermatological', 'vaccines', 'vitamins', 'supplements', 'other'];

        // Map common variations
        $mappings = [
            'antibiotic' => 'antibiotics',
            'analgesic' => 'analgesics',
            'antiviral' => 'antivirals',
            'antifungal' => 'antifungals',
            'vitamin' => 'vitamins',
            'supplement' => 'supplements',
            'general' => 'other',
        ];

        if (isset($mappings[$category])) {
            return $mappings[$category];
        }

        return in_array($category, $validCategories) ? $category : 'other';
    }

    /**
     * Normalize unit type value to valid enum.
     */
    private function normalizeUnitType(string $unitType): string
    {
        $validTypes = ['piece', 'bottle', 'vial', 'tube', 'box'];

        // Map common variations
        $mappings = [
            'tablet' => 'piece',
            'capsule' => 'piece',
            'tab' => 'piece',
            'cap' => 'piece',
            'ampoule' => 'vial',
            'amp' => 'vial',
        ];

        if (isset($mappings[$unitType])) {
            return $mappings[$unitType];
        }

        return in_array($unitType, $validTypes) ? $unitType : 'piece';
    }

    /**
     * Get value from row with flexible key matching.
     */
    private function getValue(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            // Try exact match
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }

            // Try lowercase
            $lowerKey = strtolower($key);
            if (isset($row[$lowerKey]) && $row[$lowerKey] !== '') {
                return $row[$lowerKey];
            }

            // Try with spaces replaced by underscores
            foreach ($row as $rowKey => $value) {
                if (strtolower(str_replace(' ', '_', $rowKey)) === $lowerKey && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Get results.
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
