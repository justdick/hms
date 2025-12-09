<?php

namespace App\Exports;

use App\Models\MinorProcedureType;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Procedure Type Import Template.
 *
 * Exports existing procedure types for easy editing and re-import.
 */
class ProcedureTypeImportTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ProcedureTypeImportInstructionsSheet,
            new ProcedureTypeImportDataSheet,
        ];
    }
}

class ProcedureTypeImportInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        $count = MinorProcedureType::count();

        return [
            ['PROCEDURE TYPE IMPORT/EXPORT TEMPLATE'],
            [''],
            ['ABOUT THIS FILE:'],
            [$count > 0
                ? "The Data sheet contains all {$count} procedure types currently in the system."
                : 'The Data sheet contains example rows (no procedure types exist yet).'],
            [''],
            ['HOW TO USE:'],
            ['1. Review the Data sheet - it shows your current procedure types with prices'],
            ['2. Edit prices or other fields as needed'],
            ['3. Add new rows for new procedure types'],
            ['4. Save and import the file to update all procedure types in bulk'],
            [''],
            ['IMPORT BEHAVIOR:'],
            ['- Existing procedures (matched by code) will be UPDATED'],
            ['- New codes will CREATE new procedure types'],
            ['- Required columns: code, name'],
            ['- Price defaults to 0 if not provided'],
            [''],
            ['COLUMN EXPLANATIONS:'],
            [''],
            ['code: Your unique code for the procedure (REQUIRED)'],
            ['name: Full procedure name (REQUIRED)'],
            ['category: Procedure category (e.g., Wound Care, Catheterization, etc.)'],
            ['type: minor or major'],
            ['price: Your hospital cash price for uninsured patients (GHS)'],
            ['nhis_price: NHIS tariff price (READ-ONLY - from G-DRG tariffs, cannot be edited here)'],
            ['description: Additional details about the procedure'],
            ['nhis_code: NHIS/G-DRG tariff code for auto-mapping'],
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
        ];
    }
}

class ProcedureTypeImportDataSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private int $rowCount = 0;

    public function headings(): array
    {
        return [
            'code',
            'name',
            'category',
            'type',
            'price',
            'nhis_price (read-only)',
            'description',
            'nhis_code',
        ];
    }

    public function array(): array
    {
        // Export existing procedure types for easy editing
        $procedures = MinorProcedureType::query()
            ->with('nhisMapping.gdrgTariff')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // If no procedures exist, provide example rows
        if ($procedures->isEmpty()) {
            $this->rowCount = 3;

            return [
                ['WD001', 'Wound Dressing', 'Wound Care', 'minor', '50.00', '', 'Basic wound dressing', ''],
                ['SR001', 'Suture Removal', 'Wound Care', 'minor', '40.00', '', 'Removal of surgical sutures', ''],
                ['CC-URN', 'Catheter Change (Urinary)', 'Catheterization', 'minor', '80.00', '', 'Urinary catheter change', ''],
            ];
        }

        $this->rowCount = $procedures->count();

        return $procedures->map(function ($procedure) {
            // Get NHIS code and price from mapping if exists
            $nhisCode = null;
            $nhisPrice = null;

            if ($procedure->nhisMapping && $procedure->nhisMapping->gdrgTariff) {
                $nhisCode = $procedure->nhisMapping->gdrgTariff->code;
                $nhisPrice = $procedure->nhisMapping->gdrgTariff->tariff_price;
            }

            return [
                $procedure->code,
                $procedure->name,
                $procedure->category,
                $procedure->type,
                number_format($procedure->price, 2, '.', ''),
                $nhisPrice ? number_format($nhisPrice, 2, '.', '') : '',
                $procedure->description,
                $nhisCode,
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
            'A' => 15,
            'B' => 45,
            'C' => 20,
            'D' => 10,
            'E' => 12,
            'F' => 18,
            'G' => 40,
            'H' => 15,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Enable sheet protection (allows editing unlocked cells only)
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('');

        // Unlock all columns except F (nhis_price) so users can edit them
        $lastRow = $this->rowCount + 1; // +1 for header
        foreach (['A', 'B', 'C', 'D', 'E', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'F' => [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
                'font' => [
                    'color' => ['rgb' => '64748B'],
                ],
            ],
        ];
    }
}
