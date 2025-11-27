<?php

namespace App\Exports;

use App\Models\BillingService;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\LabService;
use App\Models\NhisItemMapping;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * NHIS Coverage Template Export
 *
 * This export generates a template for NHIS coverage rules with pre-filled
 * NHIS tariff prices from the Master. Only copay amounts are editable.
 *
 * Requirements: 6.1, 6.2
 */
class NhisCoverageTemplate implements WithMultipleSheets
{
    public function __construct(
        private readonly string $category,
        private readonly int $insurancePlanId
    ) {}

    public function sheets(): array
    {
        return [
            new NhisCoverageInstructionsSheet($this->category),
            new NhisCoverageDataSheet($this->category, $this->insurancePlanId),
        ];
    }
}

class NhisCoverageInstructionsSheet implements \Maatwebsite\Excel\Concerns\FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $category
    ) {}

    public function array(): array
    {
        return [
            ['NHIS Coverage Template - Pre-Populated'],
            [''],
            ['INSTRUCTIONS:'],
            ['1. This template is PRE-FILLED with all items from your system inventory'],
            ['2. NHIS tariff prices are automatically populated from the NHIS Tariff Master'],
            ['3. You can ONLY edit the copay_amount column - tariff prices come from the Master'],
            ['4. Delete rows for items that should use the general/default rule'],
            [''],
            ['COLUMN EXPLANATIONS:'],
            [''],
            ['item_code: Hospital item code (DO NOT MODIFY)'],
            ['item_name: Item name (DO NOT MODIFY)'],
            ['hospital_price: Hospital standard price for non-insured patients (DO NOT MODIFY)'],
            ['nhis_tariff_price: NHIS reimbursement price from Master (READ-ONLY - comes from NHIS Tariff Master)'],
            ['copay_amount: Fixed amount patient pays in addition to NHIS coverage (EDITABLE)'],
            [''],
            ['HOW NHIS COVERAGE WORKS:'],
            [''],
            ['For NHIS patients:'],
            ['  - Insurance pays: NHIS Tariff Master price (not hospital price)'],
            ['  - Patient pays: Copay amount only (no percentage calculation)'],
            ['  - Hospital receives: NHIS tariff price + copay amount'],
            [''],
            ['EXAMPLE:'],
            ['  Hospital Price: GHS 50.00'],
            ['  NHIS Tariff Price: GHS 30.00 (from Master)'],
            ['  Copay Amount: GHS 5.00'],
            ['  Result: NHIS pays GHS 30.00, Patient pays GHS 5.00, Hospital gets GHS 35.00'],
            [''],
            ['IMPORTANT NOTES:'],
            ['- Items marked "NOT MAPPED" have no NHIS tariff - they are not covered by NHIS'],
            ['- Tariff prices cannot be edited here - they must be updated in the NHIS Tariff Master'],
            ['- Only copay amounts are saved when importing this file'],
            ['- Leave copay_amount empty or 0 for items with no patient copay'],
            [''],
            ['Category: '.ucfirst($this->category)],
            ['Generated: '.now()->format('Y-m-d H:i:s')],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 90,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            10 => ['font' => ['bold' => true, 'size' => 12]],
            17 => ['font' => ['bold' => true, 'size' => 12]],
            24 => ['font' => ['bold' => true, 'size' => 12]],
            31 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}

class NhisCoverageDataSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    private int $rowCount = 0;

    public function __construct(
        private readonly string $category,
        private readonly int $insurancePlanId
    ) {}

    public function collection(): Collection
    {
        // Get all items for this category from system inventory
        $items = $this->getItemsForCategory($this->category);

        // Get existing coverage rules for this plan
        $existingRules = InsuranceCoverageRule::where('insurance_plan_id', $this->insurancePlanId)
            ->where('coverage_category', $this->category)
            ->whereNotNull('item_code')
            ->get()
            ->keyBy('item_code');

        // Get NHIS item mappings for this category
        $nhisItemType = $this->mapCategoryToNhisItemType($this->category);
        $nhisMappings = NhisItemMapping::where('item_type', $nhisItemType)
            ->with('nhisTariff')
            ->get()
            ->keyBy('item_code');

        // Map items to template rows
        $collection = $items->map(function ($item) use ($existingRules, $nhisMappings) {
            $existingRule = $existingRules->get($item->code);
            $nhisMapping = $nhisMappings->get($item->code);

            // Get NHIS tariff price from Master if mapped
            $nhisTariffPrice = null;
            if ($nhisMapping && $nhisMapping->nhisTariff && $nhisMapping->nhisTariff->is_active) {
                $nhisTariffPrice = $nhisMapping->nhisTariff->price;
            }

            return [
                'item_code' => $item->code,
                'item_name' => $item->name,
                'hospital_price' => number_format($item->price, 2, '.', ''),
                'nhis_tariff_price' => $nhisTariffPrice !== null
                    ? number_format($nhisTariffPrice, 2, '.', '')
                    : 'NOT MAPPED',
                'copay_amount' => $existingRule?->patient_copay_amount
                    ? number_format($existingRule->patient_copay_amount, 2, '.', '')
                    : '',
            ];
        });

        $this->rowCount = $collection->count();

        return $collection;
    }

    /**
     * Map coverage category to NHIS item type.
     */
    private function mapCategoryToNhisItemType(string $category): string
    {
        return match ($category) {
            'drug' => 'drug',
            'lab' => 'lab_service',
            'procedure' => 'procedure',
            'consultation' => 'consultation',
            'consumable' => 'consumable',
            default => $category,
        };
    }

    private function getItemsForCategory(string $category): Collection
    {
        return match ($category) {
            'drug' => Drug::select('drug_code as code', 'name', 'unit_price as price')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'lab' => LabService::select('code', 'name', 'price')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'consultation' => BillingService::select('service_code as code', 'service_name as name', 'base_price as price')
                ->where('service_type', 'consultation')
                ->where('is_active', true)
                ->orderBy('service_name')
                ->get(),
            'procedure' => BillingService::select('service_code as code', 'service_name as name', 'base_price as price')
                ->where('service_type', 'procedure')
                ->where('is_active', true)
                ->orderBy('service_name')
                ->get(),
            'ward' => BillingService::select('service_code as code', 'service_name as name', 'base_price as price')
                ->where('service_type', 'ward')
                ->where('is_active', true)
                ->orderBy('service_name')
                ->get(),
            'nursing' => BillingService::select('service_code as code', 'service_name as name', 'base_price as price')
                ->where('service_type', 'nursing')
                ->where('is_active', true)
                ->orderBy('service_name')
                ->get(),
            default => collect([]),
        };
    }

    public function headings(): array
    {
        return [
            'item_code',
            'item_name',
            'hospital_price',
            'nhis_tariff_price',
            'copay_amount',
        ];
    }

    public function title(): string
    {
        return 'Data';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // item_code
            'B' => 45,  // item_name
            'C' => 18,  // hospital_price
            'D' => 20,  // nhis_tariff_price
            'E' => 18,  // copay_amount
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];

        // Style the nhis_tariff_price column as read-only (light gray background)
        if ($this->rowCount > 0) {
            $lastRow = $this->rowCount + 1;
            $sheet->getStyle("D2:D{$lastRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF5F5F5');

            // Also make hospital_price read-only appearance
            $sheet->getStyle("C2:C{$lastRow}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF5F5F5');

            // Highlight "NOT MAPPED" cells in orange
            for ($row = 2; $row <= $lastRow; $row++) {
                $cellValue = $sheet->getCell("D{$row}")->getValue();
                if ($cellValue === 'NOT MAPPED') {
                    $sheet->getStyle("D{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFFEB9C');
                    $sheet->getStyle("D{$row}")->getFont()
                        ->getColor()->setARGB('FFFF6600');
                }
            }
        }

        return $styles;
    }
}
