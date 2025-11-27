<?php

namespace App\Exports;

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export unmapped items for NHIS mapping.
 */
class UnmappedItemsExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
{
    public function __construct(
        private readonly ?string $itemType = null
    ) {}

    public function collection(): Collection
    {
        $items = collect();

        // Get mapped item IDs by type
        $mappedByType = NhisItemMapping::query()
            ->select('item_type', 'item_id')
            ->get()
            ->groupBy('item_type')
            ->map(fn ($group) => $group->pluck('item_id')->toArray());

        // Drugs
        if (! $this->itemType || $this->itemType === 'drug') {
            $mappedDrugIds = $mappedByType->get('drug', []);
            $drugs = Drug::whereNotIn('id', $mappedDrugIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($drug) => [
                    'item_type' => 'drug',
                    'item_code' => $drug->drug_code,
                    'item_name' => $drug->name,
                    'nhis_code' => '',
                ]);
            $items = $items->concat($drugs);
        }

        // Lab Services
        if (! $this->itemType || $this->itemType === 'lab_service') {
            $mappedLabIds = $mappedByType->get('lab_service', []);
            $labs = LabService::whereNotIn('id', $mappedLabIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($lab) => [
                    'item_type' => 'lab_service',
                    'item_code' => $lab->code,
                    'item_name' => $lab->name,
                    'nhis_code' => '',
                ]);
            $items = $items->concat($labs);
        }

        // Procedures
        if (! $this->itemType || $this->itemType === 'procedure') {
            $mappedProcIds = $mappedByType->get('procedure', []);
            $procedures = MinorProcedureType::whereNotIn('id', $mappedProcIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($proc) => [
                    'item_type' => 'procedure',
                    'item_code' => $proc->code,
                    'item_name' => $proc->name,
                    'nhis_code' => '',
                ]);
            $items = $items->concat($procedures);
        }

        return $items;
    }

    public function headings(): array
    {
        return ['item_type', 'item_code', 'item_name', 'nhis_code'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 15,
            'C' => 45,
            'D' => 15,
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
