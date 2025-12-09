<?php

namespace App\Exports;

use App\Models\LabService;
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
 * Lab Service Import Template.
 *
 * Exports existing lab services for easy editing and re-import.
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
        $labCount = LabService::count();

        return [
            ['LAB SERVICE IMPORT/EXPORT TEMPLATE'],
            [''],
            ['ABOUT THIS FILE:'],
            [$labCount > 0
                ? "The Data sheet contains all {$labCount} lab services currently in the system."
                : 'The Data sheet contains example rows (no lab services exist yet).'],
            [''],
            ['HOW TO USE:'],
            ['1. Review the Data sheet - it shows your current lab services with prices'],
            ['2. Edit prices or other fields as needed'],
            ['3. Add new rows for new lab services'],
            ['4. Save and import the file to update all services in bulk'],
            [''],
            ['IMPORT BEHAVIOR:'],
            ['- Existing services (matched by code) will be UPDATED'],
            ['- New codes will CREATE new lab services'],
            ['- Required columns: code, name'],
            ['- Price defaults to 0 if not provided'],
            [''],
            ['COLUMN EXPLANATIONS:'],
            [''],
            ['code: Your unique code for the lab test (REQUIRED)'],
            ['name: Full test name (REQUIRED)'],
            ['price: Your hospital cash price for uninsured patients (GHS)'],
            ['nhis_price: NHIS tariff price (READ-ONLY - from G-DRG tariffs, cannot be edited here)'],
            ['category: Test category - Hematology, Chemistry, Microbiology, etc.'],
            ['sample_type: Sample required - Blood, Urine, Stool, etc.'],
            ['turnaround_time: Expected time for results - 1 hour, 2 hours, etc.'],
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

class LabServiceImportDataSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private int $rowCount = 0;

    public function headings(): array
    {
        return [
            'code',
            'name',
            'price',
            'nhis_price (read-only)',
            'category',
            'sample_type',
            'turnaround_time',
            'nhis_code',
        ];
    }

    public function array(): array
    {
        // Export existing lab services for easy editing
        $labServices = LabService::query()
            ->with('nhisMapping.gdrgTariff', 'nhisMapping.nhisTariff')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // If no lab services exist, provide example rows
        if ($labServices->isEmpty()) {
            $this->rowCount = 2;

            return [
                ['LAB001', 'Full Blood Count (FBC)', '50.00', '45.00', 'Hematology', 'Blood', '2 hours', 'INVE51D'],
                ['LAB002', 'Fasting Blood Sugar', '25.00', '20.00', 'Chemistry', 'Blood', '1 hour', 'INVE46D'],
            ];
        }

        $this->rowCount = $labServices->count();

        return $labServices->map(function ($service) {
            // Get NHIS code and price from mapping if exists
            $nhisCode = null;
            $nhisPrice = null;

            if ($service->nhisMapping) {
                if ($service->nhisMapping->gdrgTariff) {
                    $nhisCode = $service->nhisMapping->gdrgTariff->code;
                    $nhisPrice = $service->nhisMapping->gdrgTariff->tariff_price;
                } elseif ($service->nhisMapping->nhisTariff) {
                    $nhisCode = $service->nhisMapping->nhisTariff->nhis_code;
                    $nhisPrice = $service->nhisMapping->nhisTariff->price ?? null;
                }
            }

            return [
                $service->code,
                $service->name,
                number_format($service->price, 2, '.', ''),
                $nhisPrice ? number_format($nhisPrice, 2, '.', '') : '',
                $service->category,
                $service->sample_type,
                $service->turnaround_time,
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
            'C' => 12,
            'D' => 18,
            'E' => 18,
            'F' => 15,
            'G' => 18,
            'H' => 15,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Enable sheet protection (allows editing unlocked cells only)
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('');

        // Unlock all columns except D (nhis_price) so users can edit them
        $lastRow = $this->rowCount + 1; // +1 for header
        foreach (['A', 'B', 'C', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}1:{$col}{$lastRow}")
                ->getProtection()
                ->setLocked(Protection::PROTECTION_UNPROTECTED);
        }

        // Column D (nhis_price) stays locked by default

        // Style header row and make NHIS price column visually distinct (locked/read-only)
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'D' => [
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
