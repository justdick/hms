<?php

namespace App\Exports;

use App\Models\DrugBatch;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Inventory Export - exports all drug batches with stock.
 *
 * Use this to backup inventory before migration.
 */
class InventoryExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new InventoryInstructionsSheet,
            new InventoryDataSheet,
        ];
    }
}

class InventoryInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        $batchCount = DrugBatch::where('quantity_remaining', '>', 0)->count();

        return [
            ['INVENTORY EXPORT/IMPORT'],
            [''],
            ['PURPOSE:'],
            ['Export current inventory (drug batches) before migration.'],
            ['Import after fresh migration to restore stock levels.'],
            [''],
            ['ABOUT THIS FILE:'],
            [$batchCount > 0
                ? "The Data sheet contains {$batchCount} batches with remaining stock."
                : 'No batches with stock found.'],
            [''],
            ['HOW TO USE FOR MIGRATION:'],
            ['1. Export this file BEFORE running migrate:fresh'],
            ['2. Run migrate:fresh and migrate:all-from-mittag'],
            ['3. Import drugs first (they must exist before importing batches)'],
            ['4. Import this inventory file to restore stock levels'],
            [''],
            ['IMPORT BEHAVIOR:'],
            ['- Drugs are matched by drug_code (must exist in system)'],
            ['- Suppliers are matched by name (created if not exists)'],
            ['- Batches are matched by drug_code + batch_number'],
            ['- Existing batches will be UPDATED'],
            ['- New batches will be CREATED'],
            [''],
            ['REQUIRED COLUMNS:'],
            ['- drug_code: Must match existing drug'],
            ['- batch_number: Unique identifier for the batch'],
            ['- quantity_remaining: Current stock level'],
            ['- expiry_date: Format YYYY-MM-DD'],
            [''],
            ['Generated: '.now()->format('Y-m-d H:i:s')],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function columnWidths(): array
    {
        return ['A' => 80];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            10 => ['font' => ['bold' => true, 'size' => 12]],
            16 => ['font' => ['bold' => true, 'size' => 12]],
            22 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}

class InventoryDataSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function headings(): array
    {
        return [
            'drug_code',
            'drug_name',
            'batch_number',
            'supplier_name',
            'quantity_received',
            'quantity_remaining',
            'cost_per_unit',
            'selling_price_per_unit',
            'expiry_date',
            'manufacture_date',
            'received_date',
            'notes',
        ];
    }

    public function array(): array
    {
        $batches = DrugBatch::query()
            ->with(['drug:id,drug_code,name', 'supplier:id,name'])
            ->where('quantity_remaining', '>', 0)
            ->orderBy('drug_id')
            ->orderBy('expiry_date')
            ->get();

        if ($batches->isEmpty()) {
            return [];
        }

        return $batches->map(function ($batch) {
            return [
                $batch->drug->drug_code ?? '',
                $batch->drug->name ?? '',
                $batch->batch_number,
                $batch->supplier->name ?? '',
                $batch->quantity_received,
                $batch->quantity_remaining,
                number_format($batch->cost_per_unit ?? 0, 2, '.', ''),
                number_format($batch->selling_price_per_unit ?? 0, 2, '.', ''),
                $batch->expiry_date?->format('Y-m-d') ?? '',
                $batch->manufacture_date?->format('Y-m-d') ?? '',
                $batch->received_date?->format('Y-m-d') ?? '',
                $batch->notes ?? '',
            ];
        })->toArray();
    }

    public function title(): string
    {
        return 'Data';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // drug_code
            'B' => 40,  // drug_name
            'C' => 20,  // batch_number
            'D' => 25,  // supplier_name
            'E' => 18,  // quantity_received
            'F' => 18,  // quantity_remaining
            'G' => 15,  // cost_per_unit
            'H' => 20,  // selling_price_per_unit
            'I' => 15,  // expiry_date
            'J' => 15,  // manufacture_date
            'K' => 15,  // received_date
            'L' => 30,  // notes
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];
    }
}
