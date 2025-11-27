<?php

namespace App\Imports;

use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Illuminate\Support\Facades\DB;

/**
 * Procedure Type Import with optional NHIS auto-mapping.
 */
class ProcedureTypeImport
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
        $code = $this->getValue($row, ['code', 'procedure_code']);
        $name = $this->getValue($row, ['name', 'procedure_name']);
        $price = $this->getValue($row, ['price', 'unit_price', 'cost']);

        if (empty($code)) {
            throw new \Exception('Missing code');
        }

        if (empty($name)) {
            throw new \Exception('Missing name');
        }

        // price is optional - default to 0 if not provided
        if (! empty($price) && ! is_numeric($price)) {
            throw new \Exception('Invalid price (must be numeric)');
        }
        $price = ! empty($price) && is_numeric($price) ? (float) $price : 0;

        $category = $this->getValue($row, ['category']) ?: 'General';
        $type = $this->getValue($row, ['type']) ?: 'minor';
        $nhisCode = $this->getValue($row, ['nhis_code']) ?: null;
        $description = $this->getValue($row, ['description']) ?: null;

        // Normalize type
        $type = $this->normalizeType(strtolower(trim($type)));

        $procedureType = MinorProcedureType::updateOrCreate(
            ['code' => trim($code)],
            [
                'name' => trim($name),
                'price' => $price,
                'category' => trim($category),
                'type' => $type,
                'description' => $description ? trim($description) : null,
                'is_active' => true,
            ]
        );

        $procedureType->wasRecentlyCreated ? $this->results['created']++ : $this->results['updated']++;

        if ($nhisCode) {
            $this->createNhisMapping($procedureType, trim($nhisCode), $rowNumber);
        }
    }

    private function createNhisMapping(MinorProcedureType $procedureType, string $nhisCode, int $rowNumber): void
    {
        // Check in both NhisTariff (medicines) and GdrgTariff (procedures)
        $nhisTariff = NhisTariff::where('nhis_code', $nhisCode)->first();

        if (! $nhisTariff) {
            // Try GdrgTariff
            $gdrgTariff = \App\Models\GdrgTariff::where('code', $nhisCode)->first();

            if (! $gdrgTariff) {
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => "NHIS code '{$nhisCode}' not found (procedure created without mapping)",
                ];

                return;
            }

            // Map to G-DRG tariff
            NhisItemMapping::updateOrCreate(
                [
                    'item_type' => 'procedure',
                    'item_id' => $procedureType->id,
                ],
                [
                    'item_code' => $procedureType->code,
                    'gdrg_tariff_id' => $gdrgTariff->id,
                ]
            );

            $this->results['mapped']++;

            return;
        }

        NhisItemMapping::updateOrCreate(
            [
                'item_type' => 'procedure',
                'item_id' => $procedureType->id,
            ],
            [
                'item_code' => $procedureType->code,
                'nhis_tariff_id' => $nhisTariff->id,
            ]
        );

        $this->results['mapped']++;
    }

    private function normalizeType(string $type): string
    {
        $validTypes = ['minor', 'major'];

        return in_array($type, $validTypes) ? $type : 'minor';
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
