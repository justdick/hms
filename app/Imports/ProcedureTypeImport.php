<?php

namespace App\Imports;

use App\Models\GdrgTariff;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
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
        $price = $this->getValue($row, ['price', 'unit_price', 'cash_price']);

        if (empty($code)) {
            throw new \Exception('Missing code');
        }

        if (empty($name)) {
            throw new \Exception('Missing name');
        }

        if (! empty($price) && ! is_numeric($price)) {
            throw new \Exception('Invalid price (must be numeric)');
        }
        $price = ! empty($price) && is_numeric($price) ? (float) $price : 0;

        $category = $this->getValue($row, ['category']) ?: 'General';
        $type = $this->getValue($row, ['type']) ?: 'minor';
        $description = $this->getValue($row, ['description']) ?: null;
        $nhisCode = $this->getValue($row, ['nhis_code']) ?: null;

        // Normalize type
        $type = strtolower(trim($type));
        if (! in_array($type, ['minor', 'major'])) {
            $type = 'minor';
        }

        $procedure = MinorProcedureType::updateOrCreate(
            ['code' => trim($code)],
            [
                'name' => trim($name),
                'category' => trim($category),
                'type' => $type,
                'description' => $description ? trim($description) : null,
                'price' => $price,
                'is_active' => true,
            ]
        );

        $procedure->wasRecentlyCreated ? $this->results['created']++ : $this->results['updated']++;

        if ($nhisCode) {
            $this->createNhisMapping($procedure, trim($nhisCode), $rowNumber);
        }
    }

    private function createNhisMapping(MinorProcedureType $procedure, string $nhisCode, int $rowNumber): void
    {
        // Procedures use G-DRG tariffs
        $gdrgTariff = GdrgTariff::where('code', $nhisCode)->first();

        if (! $gdrgTariff) {
            $this->results['errors'][] = [
                'row' => $rowNumber,
                'error' => "G-DRG code '{$nhisCode}' not found (procedure created without mapping)",
            ];

            return;
        }

        NhisItemMapping::updateOrCreate(
            [
                'item_type' => 'procedure',
                'item_id' => $procedure->id,
            ],
            [
                'item_code' => $procedure->code,
                'gdrg_tariff_id' => $gdrgTariff->id,
            ]
        );

        $this->results['mapped']++;
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
