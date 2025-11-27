<?php

namespace App\Imports;

use App\Models\GdrgTariff;
use App\Models\LabService;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Illuminate\Support\Facades\DB;

/**
 * Lab Service Import with optional NHIS auto-mapping.
 */
class LabServiceImport
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
     */
    public function processRows(array $rows): array
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

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

    private function processRow(array $row, int $rowNumber): void
    {
        $code = $this->getValue($row, ['code', 'lab_code', 'service_code']);
        $name = $this->getValue($row, ['name', 'service_name', 'test_name']);
        $price = $this->getValue($row, ['price', 'unit_price', 'cost']);

        if (empty($code)) {
            throw new \Exception('Missing code');
        }

        if (empty($name)) {
            throw new \Exception('Missing name');
        }

        // price is optional - default to 0 if not provided (can be updated later)
        if (! empty($price) && ! is_numeric($price)) {
            throw new \Exception('Invalid price (must be numeric)');
        }
        $price = ! empty($price) && is_numeric($price) ? (float) $price : 0;

        $category = $this->getValue($row, ['category']) ?: 'General';
        $sampleType = $this->getValue($row, ['sample_type']) ?: null;
        $turnaroundTime = $this->getValue($row, ['turnaround_time']) ?: null;
        $nhisCode = $this->getValue($row, ['nhis_code']) ?: null;
        $description = $this->getValue($row, ['description']) ?: null;

        $labService = LabService::updateOrCreate(
            ['code' => trim($code)],
            [
                'name' => trim($name),
                'price' => $price,
                'category' => trim($category),
                'sample_type' => $sampleType ? trim($sampleType) : null,
                'turnaround_time' => $turnaroundTime ? trim($turnaroundTime) : null,
                'description' => $description ? trim($description) : null,
                'is_active' => true,
            ]
        );

        $labService->wasRecentlyCreated ? $this->results['created']++ : $this->results['updated']++;

        if ($nhisCode) {
            $this->createNhisMapping($labService, trim($nhisCode), $rowNumber);
        }
    }

    private function createNhisMapping(LabService $labService, string $nhisCode, int $rowNumber): void
    {
        // Lab services (investigations) are in G-DRG tariffs, not NHIS tariffs
        $gdrgTariff = GdrgTariff::where('code', $nhisCode)->first();

        if ($gdrgTariff) {
            NhisItemMapping::updateOrCreate(
                [
                    'item_type' => 'lab_service',
                    'item_id' => $labService->id,
                ],
                [
                    'item_code' => $labService->code,
                    'gdrg_tariff_id' => $gdrgTariff->id,
                ]
            );

            $this->results['mapped']++;

            return;
        }

        // Fallback: check NHIS tariffs
        $nhisTariff = NhisTariff::where('nhis_code', $nhisCode)->first();

        if ($nhisTariff) {
            NhisItemMapping::updateOrCreate(
                [
                    'item_type' => 'lab_service',
                    'item_id' => $labService->id,
                ],
                [
                    'item_code' => $labService->code,
                    'nhis_tariff_id' => $nhisTariff->id,
                ]
            );

            $this->results['mapped']++;

            return;
        }

        $this->results['errors'][] = [
            'row' => $rowNumber,
            'error' => "NHIS code '{$nhisCode}' not found (lab service created without mapping)",
        ];
    }

    private function getValue(array $row, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }

            $lowerKey = strtolower($key);
            if (isset($row[$lowerKey]) && $row[$lowerKey] !== '') {
                return $row[$lowerKey];
            }
        }

        return null;
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
