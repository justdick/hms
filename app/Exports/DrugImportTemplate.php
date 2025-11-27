<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Drug Import Template with instructions and example data.
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
        return [
            ['DRUG IMPORT TEMPLATE'],
            [''],
            ['INSTRUCTIONS:'],
            ['1. Fill in the Data sheet with your drug information'],
            ['2. Required columns: drug_code, name, unit_price'],
            ['3. Optional columns: generic_name, form, strength, category, unit_type, bottle_size, min_stock, max_stock, nhis_code'],
            ['4. If nhis_code is provided and valid, NHIS mapping will be auto-created'],
            ['5. Existing drugs (by drug_code) will be updated, new ones created'],
            [''],
            ['COLUMN EXPLANATIONS:'],
            [''],
            ['drug_code: Your unique hospital code for the drug (REQUIRED)'],
            ['name: Full drug name with strength (REQUIRED)'],
            ['generic_name: Generic/INN name (optional)'],
            ['form: Dosage form (optional, defaults to "other") - see VALID VALUES below'],
            ['strength: Drug strength - 250mg, 500mg, etc. (optional)'],
            ['unit_price: Your hospital selling price in GHS (REQUIRED)'],
            ['unit_type: Unit of sale (optional, defaults to "piece") - see VALID VALUES below'],
            ['bottle_size: Volume in ml for bottles/vials (optional) - e.g., 100 for 100ml syrup, 10 for 10ml vial'],
            ['category: Drug category (optional, defaults to "other") - see VALID VALUES below'],
            ['min_stock: Minimum stock level for alerts (optional, defaults to 10)'],
            ['max_stock: Maximum stock level (optional, defaults to 1000)'],
            ['nhis_code: NHIS tariff code for auto-mapping (optional)'],
            [''],
            ['VALID VALUES FOR FORM:'],
            ['tablet, capsule, syrup, suspension, injection, drops, cream, ointment, inhaler, patch, other'],
            [''],
            ['VALID VALUES FOR CATEGORY:'],
            ['analgesics, antibiotics, antivirals, antifungals, cardiovascular, diabetes, respiratory,'],
            ['gastrointestinal, neurological, psychiatric, dermatological, vaccines, vitamins, supplements, other'],
            [''],
            ['VALID VALUES FOR UNIT_TYPE:'],
            ['piece, bottle, vial, tube, box'],
            [''],
            ['NHIS AUTO-MAPPING:'],
            [''],
            ['If you provide a valid nhis_code:'],
            ['  - System will automatically link your drug to the NHIS tariff'],
            ['  - NHIS patients will get coverage based on NHIS tariff price'],
            ['  - If nhis_code is invalid/not found, drug is created without mapping'],
            [''],
            ['TIPS:'],
            ['- Use NHIS codes from the NHIS Medicines List (e.g., AMOXICCA1)'],
            ['- You can leave nhis_code empty and map later via NHIS Mappings page'],
            ['- Delete the example rows before importing your data'],
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
    public function headings(): array
    {
        return [
            'drug_code',
            'name',
            'generic_name',
            'form',
            'strength',
            'unit_price',
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
        // Example rows
        return [
            [
                'DRG001',
                'Amoxicillin 250mg Capsules',
                'Amoxicillin',
                'capsule',
                '250mg',
                '2.50',
                'piece',
                '',  // bottle_size - not needed for tablets/capsules
                'antibiotics',
                '10',
                '500',
                'AMOXICCA1',
            ],
            [
                'DRG002',
                'Paracetamol 500mg Tablets',
                'Paracetamol',
                'tablet',
                '500mg',
                '1.50',
                'piece',
                '',  // bottle_size
                'analgesics',
                '',
                '',
                '',
            ],
            [
                'DRG003',
                'Ibuprofen Suspension 100mg/5ml',
                'Ibuprofen',
                'suspension',
                '100mg/5ml',
                '15.00',
                'bottle',
                '100',  // 100ml bottle
                'analgesics',
                '5',
                '100',
                '',
            ],
            [
                'DRG004',
                'Gentamicin Injection 80mg/2ml',
                'Gentamicin',
                'injection',
                '80mg/2ml',
                '8.00',
                'vial',
                '2',  // 2ml vial
                'antibiotics',
                '10',
                '200',
                '',
            ],
        ];
    }

    public function title(): string
    {
        return 'Data';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,  // drug_code
            'B' => 35,  // name
            'C' => 20,  // generic_name
            'D' => 12,  // form
            'E' => 12,  // strength
            'F' => 12,  // unit_price
            'G' => 12,  // unit_type
            'H' => 12,  // bottle_size
            'I' => 15,  // category
            'J' => 10,  // min_stock
            'K' => 10,  // max_stock
            'L' => 15,  // nhis_code
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
