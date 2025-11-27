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
 * Lab Service Import Template.
 */
class LabServiceImportTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new LabServiceImportInstructionsSheet,
            new LabServiceImportDataSheet,
        ];
    }
}

class LabServiceImportInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function array(): array
    {
        return [
            ['LAB SERVICE IMPORT TEMPLATE'],
            [''],
            ['INSTRUCTIONS:'],
            ['1. Fill in the Data sheet with your lab service information'],
            ['2. Required columns: code, name, price'],
            ['3. Optional columns: category, sample_type, turnaround_time, nhis_code'],
            ['4. If nhis_code is provided and valid, NHIS mapping will be auto-created'],
            ['5. Existing services (by code) will be updated, new ones created'],
            [''],
            ['COLUMN EXPLANATIONS:'],
            [''],
            ['code: Your unique code for the lab test (REQUIRED)'],
            ['name: Full test name (REQUIRED)'],
            ['price: Your hospital price in GHS (REQUIRED)'],
            ['category: Test category - Hematology, Chemistry, Microbiology, etc. (optional)'],
            ['sample_type: Sample required - Blood, Urine, Stool, etc. (optional)'],
            ['turnaround_time: Expected time for results - 1 hour, 2 hours, etc. (optional)'],
            ['nhis_code: NHIS tariff code for auto-mapping (optional)'],
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

class LabServiceImportDataSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public function headings(): array
    {
        return ['code', 'name', 'price', 'category', 'sample_type', 'turnaround_time', 'nhis_code'];
    }

    public function array(): array
    {
        return [
            ['LAB001', 'Full Blood Count (FBC)', '50.00', 'Hematology', 'Blood', '2 hours', 'INVE51D'],
            ['LAB002', 'Fasting Blood Sugar', '25.00', 'Chemistry', 'Blood', '1 hour', 'INVE46D'],
        ];
    }

    public function title(): string
    {
        return 'Data';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 35,
            'C' => 12,
            'D' => 15,
            'E' => 12,
            'F' => 15,
            'G' => 12,
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
