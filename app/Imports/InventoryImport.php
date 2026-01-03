<?php

namespace App\Imports;

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\Supplier;
use Carbon\Carbon;

/**
 * Inventory Import - imports drug batches from export file.
 *
 * Use this to restore inventory after migration.
 */
class InventoryImport
{
    private array $results = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    private array $drugCache = [];

    private array $supplierCache = [];

    public function processRows(array $rows): array
    {
        // Pre-load drugs for faster lookup
        $this->drugCache = Drug::pluck('id', 'drug_code')->toArray();
        $this->supplierCache = Supplier::pluck('id', 'name')->toArray();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for header and 0-index

            try {
                $this->processRow($row, $rowNumber);
            } catch (\Exception $e) {
                $this->results['errors'][] = "Row {$rowNumber}: {$e->getMessage()}";
                $this->results['skipped']++;
            }
        }

        return $this->results;
    }

    private function processRow(array $row, int $rowNumber): void
    {
        $drugCode = trim($row['drug_code'] ?? '');
        $batchNumber = trim($row['batch_number'] ?? '');
        $quantityRemaining = (int) ($row['quantity_remaining'] ?? 0);

        // Validate required fields
        if (empty($drugCode)) {
            throw new \Exception('Missing drug_code');
        }

        if (empty($batchNumber)) {
            throw new \Exception('Missing batch_number');
        }

        // Find drug
        $drugId = $this->drugCache[$drugCode] ?? null;
        if (! $drugId) {
            throw new \Exception("Drug not found: {$drugCode}");
        }

        // Find or create supplier
        $supplierId = null;
        $supplierName = trim($row['supplier_name'] ?? '');
        if (! empty($supplierName)) {
            $supplierId = $this->getOrCreateSupplier($supplierName);
        }

        // Parse dates
        $expiryDate = $this->parseDate($row['expiry_date'] ?? null);
        $manufactureDate = $this->parseDate($row['manufacture_date'] ?? null);
        $receivedDate = $this->parseDate($row['received_date'] ?? null);

        if (! $expiryDate) {
            throw new \Exception('Invalid or missing expiry_date');
        }

        // Check for existing batch
        $existingBatch = DrugBatch::where('drug_id', $drugId)
            ->where('batch_number', $batchNumber)
            ->first();

        $data = [
            'drug_id' => $drugId,
            'batch_number' => $batchNumber,
            'quantity_received' => (int) ($row['quantity_received'] ?? $quantityRemaining),
            'quantity_remaining' => $quantityRemaining,
            'cost_per_unit' => $this->parseDecimal($row['cost_per_unit'] ?? null),
            'selling_price_per_unit' => $this->parseDecimal($row['selling_price_per_unit'] ?? null),
            'expiry_date' => $expiryDate,
            'manufacture_date' => $manufactureDate,
            'received_date' => $receivedDate ?? now(),
            'notes' => trim($row['notes'] ?? '') ?: null,
        ];

        // Only include supplier_id if we have one (for new batches, it's required)
        if ($supplierId) {
            $data['supplier_id'] = $supplierId;
        }

        if ($existingBatch) {
            // For updates, only update supplier if provided
            $existingBatch->update($data);
            $this->results['updated']++;
        } else {
            // For new batches, supplier is required
            if (! $supplierId) {
                throw new \Exception('Supplier is required for new batches');
            }
            $data['supplier_id'] = $supplierId;
            DrugBatch::create($data);
            $this->results['created']++;
        }
    }

    private function getOrCreateSupplier(string $name): int
    {
        if (isset($this->supplierCache[$name])) {
            return $this->supplierCache[$name];
        }

        // Generate a unique supplier code
        $baseCode = 'SUP-'.strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 6));
        $code = $baseCode;
        $counter = 1;
        while (Supplier::where('supplier_code', $code)->exists()) {
            $code = $baseCode.'-'.$counter;
            $counter++;
        }

        $supplier = Supplier::firstOrCreate(
            ['name' => $name],
            [
                'supplier_code' => $code,
                'contact_person' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'is_active' => true,
            ]
        );

        $this->supplierCache[$name] = $supplier->id;

        return $supplier->id;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDecimal(?string $value): ?float
    {
        if (empty($value)) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        return $cleaned !== '' ? (float) $cleaned : null;
    }
}
