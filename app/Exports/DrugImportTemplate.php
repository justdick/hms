<?php

namespace App\Exports;

use App\Models\Drug;
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
 * Drug Import Template.
 *
 * Exports existing drugs for easy editing and re-import.
 */
class DrugImportTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new DrugImportInstructionsSheet,
            new DrugImportDataSheet,
        ];
    }
}

class DrugImportInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        $drugCount = Drug::count();

        return [
            ['DRUG IMPORT/EXPORT TEMPLATE'],
            [''],
            ['ABOUT THIS FILE:'],
            [$drugCount > 0
                ? "The Data sheet contains all {$drugCount} drugs currently in the system."
                : 'The Data sheet contains example rows (no drugs exist yet).'],
            [''],
            ['HOW TO USE:'],
            ['1. Review the Data sheet - it shows your current drugs with prices'],
            ['2. Edit prices or other fields as needed'],
            ['3. Add new rows for new drugs'],
            ['4. Save and import the file to update all drugs in bulk'],
            [''],
            ['IMPORT BEHAVIOR:'],
            ['- Existing drugs (matched by drug_code) will be UPDATED'],
            ['- New codes will CREATE new drugs'],
            ['- Required columns: drug_code, name'],
            ['- Price defaults to 0 if not provided'],
            [''],
            ['COLUMN EXPLANATIONS:'],
            [''],
            ['drug_code: Your unique hospital code for the drug (REQUIRED)'],
            ['name: Full drug name with strength (REQUIRED)'],
            ['generic_name: Generic/INN name'],
            ['form: Dosage form - tablet, capsule, syrup, suspension, injection, drops, cream, ointment, inhaler, patch, other'],
            ['strength: Drug strength - 250mg, 500mg, etc.'],
            ['unit_price: Your hospital cash price for uninsured patients (GHS)'],
            ['nhis_price: NHIS tariff price (READ-ONLY - from NHIS tariffs, cannot be edited here)'],
            ['unit_type: Unit of sale - piece, bottle, vial, tube, box'],
            ['bottle_size: Volume in ml for bottles/vials'],
            ['category: analgesics, antibiotics, antivirals, antifungals, cardiovascular, diabetes, respiratory, gastrointestinal, neurological, psychiatric, dermatological, vaccines, vitamins, supplements, other'],
            ['min_stock: Minimum stock level for alerts'],
            ['max_stock: Maximum stock level'],
            ['nhis_code: NHIS tariff code for auto-mapping'],
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
            22 => ['font' => ['bold' => true, 'size' => 12]],
            29 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}

class DrugImportDataSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private int $rowCount = 0;

    public function headings(): array
    {
        return [
            'drug_code',
            'name',
            'generic_name',
            'form',
            'strength',
            'unit_price',
            'nhis_price (read-only)',
            'unit_type',
            'bottle_size',
            'category',
            'min_stock',
            'max_stock',
            'nhis_code',
        ];
    }

    public function array(): array
    {
        // Export existing drugs for easy editing
        $drugs = Drug::query()
            ->with('nhisMapping.nhisTariff')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // If no drugs exist, provide example rows
        if ($drugs->isEmpty()) {
            $this->rowCount = 4;

            return [
                ['DRG001', 'Amoxicillin 250mg Capsules', 'Amoxicillin', 'capsule', '250mg', '2.50', '1.80', 'piece', '', 'antibiotics', '10', '500', 'AMOXICCA1'],
                ['DRG002', 'Paracetamol 500mg Tablets', 'Paracetamol', 'tablet', '500mg', '1.50', '', 'piece', '', 'analgesics', '', '', ''],
                ['DRG003', 'Ibuprofen Suspension 100mg/5ml', 'Ibuprofen', 'suspension', '100mg/5ml', '15.00', '', 'bottle', '100', 'analgesics', '5', '100', ''],
                ['DRG004', 'Gentamicin Injection 80mg/2ml', 'Gentamicin', 'injection', '80mg/2ml', '8.00', '', 'vial', '2', 'antibiotics', '10', '200', ''],
            ];
        }

        $this->rowCount = $drugs->count();

        return $drugs->map(function ($drug) {
            // Get NHIS code and price from mapping if exists
            $nhisCode = null;
            $nhisPrice = null;

            if ($drug->nhisMapping && $drug->nhisMapping->nhisTariff) {
                $nhisCode = $drug->nhisMapping->nhisTariff->nhis_code;
                $nhisPrice = $drug->nhisMapping->nhisTariff->price ?? null;
            }

            return [
                $drug->drug_code,
                $drug->name,
                $drug->generic_name,
                $drug->form,
                $drug->strength,
                number_format($drug->unit_price, 2, '.', ''),
                $nhisPrice ? number_format($nhisPrice, 2, '.', '') : '',
                $drug->unit_type,
                $drug->bottle_size,
                $drug->category,
                $drug->minimum_stock_level,
                $drug->maximum_stock_level,
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
            'A' => 15,  // drug_code
            'B' => 40,  // name
            'C' => 20,  // generic_name
            'D' => 12,  // form
            'E' => 12,  // strength
            'F' => 12,  // unit_price
            'G' => 18,  // nhis_price (read-only)
            'H' => 12,  // unit_type
            'I' => 12,  // bottle_size
            'J' => 15,  // category
            'K' => 10,  // min_stock
            'L' => 10,  // max_stock
            'M' => 15,  // nhis_code
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Enable sheet protection (allows editing unlocked cells only)
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('');

        // Unlock all columns except G (nhis_price) so users can edit them
        $lastRow = $this->rowCount + 1; // +1 for header
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'H', 'I', 'J', 'K', 'L', 'M'] as $col) {
            $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        // Column G (nhis_price) stays locked by default

        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'G' => [
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
