<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProcedureTypeImportTemplate implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function headings(): array
    {
        return [
            'code',
            'name',
            'category',
            'type',
            'price',
            'description',
            'nhis_code',
        ];
    }

    public function array(): array
    {
        return [
            // Example rows
            ['PROC001', 'Wound Dressing - Simple', 'Minor Procedures', 'minor', '50.00', 'Simple wound dressing', ''],
            ['PROC002', 'Suturing - Minor', 'Minor Procedures', 'minor', '150.00', 'Minor suturing procedure', ''],
            ['PROC003', 'Incision and Drainage', 'General Surgery', 'minor', '200.00', 'I&D of abscess', 'ASUR31A'],
            ['', '', '', '', '', '', ''],
            ['INSTRUCTIONS:', '', '', '', '', '', ''],
            ['- code: Required. Unique procedure code', '', '', '', '', '', ''],
            ['- name: Required. Procedure name', '', '', '', '', '', ''],
            ['- category: Optional. E.g., General Surgery, Dental, ENT, Orthopaedic, etc.', '', '', '', '', '', ''],
            ['- type: Optional. "minor" or "major" (defaults to minor)', '', '', '', '', '', ''],
            ['- price: Optional. Hospital price (can be set later)', '', '', '', '', '', ''],
            ['- description: Optional. Procedure description', '', '', '', '', '', ''],
            ['- nhis_code: Optional. NHIS/G-DRG code for auto-mapping', '', '', '', '', '', ''],
            ['', '', '', '', '', '', ''],
            ['VALID CATEGORIES:', '', '', '', '', '', ''],
            ['General Surgery, Dental, ENT, Obstetrics & Gynaecology, Ophthalmology,', '', '', '', '', '', ''],
            ['Orthopaedic, Paediatric Surgery, Reconstructive Surgery, Minor Procedures', '', '', '', '', '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Procedure Import';
    }
}
