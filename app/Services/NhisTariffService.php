<?php

namespace App\Services;

use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NhisTariffService
{
    /**
     * Get the NHIS tariff for a specific item via item mapping.
     * Checks both nhis_tariff (medicines) and gdrg_tariff (labs/procedures).
     *
     * @param  string  $itemType  The type of item (drug, lab_service, procedure, consumable)
     * @param  int  $itemId  The ID of the item
     * @return NhisTariff|null The NHIS tariff if mapped, null otherwise
     */
    public function getTariffForItem(string $itemType, int $itemId): ?NhisTariff
    {
        $mapping = NhisItemMapping::query()
            ->forItem($itemType, $itemId)
            ->with(['nhisTariff', 'gdrgTariff'])
            ->first();

        if (! $mapping) {
            return null;
        }

        // First check NHIS tariff (medicines)
        if ($mapping->nhisTariff && $mapping->nhisTariff->is_active) {
            return $mapping->nhisTariff;
        }

        // Fall back to G-DRG tariff (labs, procedures) - convert to NhisTariff-like object
        if ($mapping->gdrgTariff && $mapping->gdrgTariff->is_active) {
            return $this->convertGdrgToNhisTariff($mapping->gdrgTariff);
        }

        return null;
    }

    /**
     * Convert a G-DRG tariff to an NhisTariff-like object for consistent handling.
     */
    protected function convertGdrgToNhisTariff($gdrgTariff): NhisTariff
    {
        // Create a virtual NhisTariff object (not persisted)
        $nhisTariff = new NhisTariff;
        $nhisTariff->id = $gdrgTariff->id;
        $nhisTariff->nhis_code = $gdrgTariff->code;
        $nhisTariff->name = $gdrgTariff->name;
        $nhisTariff->price = $gdrgTariff->tariff_price;
        $nhisTariff->category = $this->mapGdrgCategoryToNhis($gdrgTariff->mdc_category);
        $nhisTariff->is_active = $gdrgTariff->is_active;
        $nhisTariff->exists = false; // Mark as not persisted

        return $nhisTariff;
    }

    /**
     * Map G-DRG MDC category to NHIS category.
     */
    protected function mapGdrgCategoryToNhis(string $mdcCategory): string
    {
        return match (strtoupper($mdcCategory)) {
            'INVESTIGATION' => 'lab',
            'ADULT SURGERY', 'PAEDIATRIC SURGERY', 'SURGERY' => 'procedure',
            default => 'procedure',
        };
    }

    /**
     * Get the NHIS tariff price for a mapped item.
     *
     * @param  string  $itemType  The type of item (drug, lab_service, procedure, consumable)
     * @param  int  $itemId  The ID of the item
     * @return float|null The NHIS tariff price if mapped, null otherwise
     */
    public function getTariffPrice(string $itemType, int $itemId): ?float
    {
        $tariff = $this->getTariffForItem($itemType, $itemId);

        return $tariff?->price;
    }

    /**
     * Check if an item has an NHIS mapping (either NHIS tariff or G-DRG tariff).
     *
     * @param  string  $itemType  The type of item (drug, lab_service, procedure, consumable)
     * @param  int  $itemId  The ID of the item
     * @return bool True if the item is mapped to an NHIS or G-DRG tariff
     */
    public function isItemMapped(string $itemType, int $itemId): bool
    {
        return NhisItemMapping::query()
            ->forItem($itemType, $itemId)
            ->where(function ($query) {
                $query->whereHas('nhisTariff', function ($q) {
                    $q->where('is_active', true);
                })->orWhereHas('gdrgTariff', function ($q) {
                    $q->where('is_active', true);
                });
            })
            ->exists();
    }

    /**
     * Import NHIS tariffs from a CSV/Excel file.
     * Handles upsert logic - updates existing codes, creates new ones.
     *
     * @param  UploadedFile  $file  The uploaded file containing tariff data
     * @return array{success: bool, imported: int, updated: int, errors: array}
     */
    public function importTariffs(UploadedFile $file): array
    {
        $result = [
            'success' => true,
            'imported' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        // Read the file content
        $content = file_get_contents($file->getRealPath());
        $lines = array_filter(explode("\n", $content));

        if (count($lines) < 2) {
            $result['success'] = false;
            $result['errors'][] = 'File is empty or has no data rows';

            return $result;
        }

        // Parse header row
        $header = str_getcsv(array_shift($lines));
        $header = array_map('trim', array_map('strtolower', $header));

        // Validate required columns
        $requiredColumns = ['nhis_code', 'name', 'category', 'price'];
        $missingColumns = array_diff($requiredColumns, $header);

        if (! empty($missingColumns)) {
            $result['success'] = false;
            $result['errors'][] = 'Missing required columns: '.implode(', ', $missingColumns);

            return $result;
        }

        // Get column indices
        $columnIndices = array_flip($header);

        DB::beginTransaction();

        try {
            $rowNumber = 1;
            foreach ($lines as $line) {
                $rowNumber++;
                $line = trim($line);

                if (empty($line)) {
                    continue;
                }

                $row = str_getcsv($line);

                // Extract values
                $nhisCode = trim($row[$columnIndices['nhis_code']] ?? '');
                $name = trim($row[$columnIndices['name']] ?? '');
                $category = trim(strtolower($row[$columnIndices['category']] ?? ''));
                $price = trim($row[$columnIndices['price']] ?? '');
                $unit = isset($columnIndices['unit']) ? trim($row[$columnIndices['unit']] ?? '') : null;

                // Validate row data
                $validator = Validator::make([
                    'nhis_code' => $nhisCode,
                    'name' => $name,
                    'category' => $category,
                    'price' => $price,
                ], [
                    'nhis_code' => 'required|string|max:50',
                    'name' => 'required|string|max:255',
                    'category' => 'required|in:medicine,lab,procedure,consultation,consumable',
                    'price' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    $result['errors'][] = "Row {$rowNumber}: ".implode(', ', $validator->errors()->all());

                    continue;
                }

                // Upsert the tariff
                $existingTariff = NhisTariff::where('nhis_code', $nhisCode)->first();

                if ($existingTariff) {
                    $existingTariff->update([
                        'name' => $name,
                        'category' => $category,
                        'price' => (float) $price,
                        'unit' => $unit ?: $existingTariff->unit,
                    ]);
                    $result['updated']++;
                } else {
                    NhisTariff::create([
                        'nhis_code' => $nhisCode,
                        'name' => $name,
                        'category' => $category,
                        'price' => (float) $price,
                        'unit' => $unit,
                        'is_active' => true,
                    ]);
                    $result['imported']++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $result['success'] = false;
            $result['errors'][] = 'Import failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Get all unmapped items of a specific type.
     *
     * @param  string  $itemType  The type of item (drug, lab_service, procedure, consumable)
     * @return Collection Collection of unmapped items
     */
    public function getUnmappedItems(string $itemType): Collection
    {
        $modelClass = NhisItemMapping::getModelClassForType($itemType);

        if (! $modelClass) {
            return collect();
        }

        $mappedItemIds = NhisItemMapping::where('item_type', $itemType)
            ->pluck('item_id')
            ->toArray();

        return $modelClass::whereNotIn('id', $mappedItemIds)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get tariff by NHIS code.
     *
     * @param  string  $nhisCode  The NHIS code
     * @return NhisTariff|null The tariff if found
     */
    public function getTariffByCode(string $nhisCode): ?NhisTariff
    {
        return NhisTariff::where('nhis_code', $nhisCode)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Search tariffs by code, name, or category.
     *
     * @param  string|null  $search  Search term
     * @param  string|null  $category  Category filter
     * @param  int  $limit  Maximum results to return
     * @return Collection Collection of matching tariffs
     */
    public function searchTariffs(?string $search = null, ?string $category = null, int $limit = 50): Collection
    {
        return NhisTariff::query()
            ->active()
            ->search($search)
            ->byCategory($category)
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
