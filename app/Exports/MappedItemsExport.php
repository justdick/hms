<?php

namespace App\Exports;

use App\Models\NhisItemMapping;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export mapped items for backup/audit.
 */
class MappedItemsExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
{
    public function __construct(
        private readonly ?string $itemType = null
    ) {}

    public function collection(): Collection
    {
        $query = NhisItemMapping::query()
            ->with(['nhisTariff'])
            ->orderBy('item_type')
            ->orderBy('item_code');

        if ($this->itemType) {
            $query->where('item_type', $this->itemType);
        }

        return $query->get()->map(function ($mapping) {
            return [
                'item_type' => $mapping->item_type,
                'item_code' => $mapping->item_code,
                'nhis_code' => $mapping->nhisTariff?->nhis_code ?? '',
                'nhis_name' => $mapping->nhisTariff?->name ?? '',
                'nhis_price' => $mapping->nhisTariff?->price ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return ['item_type', 'item_code', 'nhis_code', 'nhis_name', 'nhis_price'];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 15,
            'C' => 15,
            'D' => 45,
            'E' => 12,
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
