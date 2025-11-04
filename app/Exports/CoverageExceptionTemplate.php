<?php

namespace App\Exports;

use App\Models\BillingService;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\LabService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CoverageExceptionTemplate implements WithMultipleSheets
{
    public function __construct(
        private readonly string $category,
        private readonly int $insurancePlanId
    ) {}

    public function sheets(): array
    {
        return [
            new EnhancedInstructionsSheet($this->category),
            new PrePopulatedDataSheet($this->category, $this->insurancePlanId),
        ];
    }
}

class EnhancedInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $category
    ) {}

    public function array(): array
    {
        return [
            ['Coverage Exception Import Template - Pre-Populated'],
            [''],
            ['INSTRUCTIONS:'],
            ['1. The Data sheet is PRE-FILLED with all items from your system inventory'],
            ['2. Review and edit ONLY the coverage_type and coverage_value columns'],
            ['3. Do NOT modify item_code, item_name, or current_price columns'],
            ['4. Delete rows for items that should use the general/default rule'],
            [''],
            ['COVERAGE TYPES:'],
            [''],
            ['Type: percentage'],
            ['  Description: Insurance covers X%, patient pays (100-X)%'],
            ['  Example: coverage_value = 80 means 80% insurance, 20% patient'],
            ['  Use for: Standard percentage-based coverage'],
            [''],
            ['Type: fixed_amount'],
            ['  Description: Insurance pays fixed dollar amount, patient pays rest'],
            ['  Example: coverage_value = 30 means insurance pays $30, patient pays (price - $30)'],
            ['  Use for: Drugs with fixed copay amounts'],
            [''],
            ['Type: full'],
            ['  Description: 100% coverage, no patient copay'],
            ['  Example: coverage_value = 100 (value ignored, always 100%)'],
            ['  Use for: Essential medications, preventive care'],
            [''],
            ['Type: excluded'],
            ['  Description: 0% coverage, patient pays all'],
            ['  Example: coverage_value = 0 (value ignored, always 0%)'],
            ['  Use for: Cosmetic items, non-covered services'],
            [''],
            ['EXAMPLES:'],
            ['  Paracetamol: percentage, 100 → 100% covered'],
            ['  Insulin: fixed_amount, 30 → Insurance pays $30, patient pays rest'],
            ['  Cosmetic cream: excluded, 0 → Not covered, patient pays all'],
            ['  Preventive vaccine: full, 100 → Fully covered, no copay'],
            [''],
            ['ERROR DETECTION:'],
            ['The template includes automatic error highlighting:'],
            [''],
            ['RED BACKGROUND (Critical Error):'],
            ['  - Percentage type with value > 100'],
            ['  Example: coverage_type=percentage, coverage_value=150 → Invalid!'],
            [''],
            ['ORANGE BACKGROUND (Warning):'],
            ['  - Fixed amount greater than item price'],
            ['  Example: price=$5, fixed_amount=$30 → Insurance pays more than cost!'],
            ['  - Suspicious fixed amount (likely meant to be percentage)'],
            ['  Example: price=$3, fixed_amount=80 → Did you mean 80% instead?'],
            [''],
            ['IMPORTANT NOTES:'],
            ['- All items are already populated - just edit coverage settings'],
            ['- Items you don\'t modify will keep their current coverage'],
            ['- Delete rows for items that should use the general rule'],
            ['- Do not add new rows - only edit existing ones'],
            ['- Check highlighted cells before importing'],
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
            'A' => 80, // Wide column for instructions
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true, 'size' => 12]],
            29 => ['font' => ['bold' => true, 'size' => 12]],
            36 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}

class PrePopulatedDataSheet implements FromCollection, WithColumnWidths, WithHeadings, WithStyles, WithTitle
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

        // Get existing specific coverage rules for this plan
        $existingRules = InsuranceCoverageRule::where('insurance_plan_id', $this->insurancePlanId)
            ->where('coverage_category', $this->category)
            ->whereNotNull('item_code')
            ->get()
            ->keyBy('item_code');

        // Get general rule for default values
        $generalRule = InsuranceCoverageRule::where('insurance_plan_id', $this->insurancePlanId)
            ->where('coverage_category', $this->category)
            ->whereNull('item_code')
            ->first();

        $defaultCoverageType = $generalRule?->coverage_type ?? 'percentage';
        $defaultCoverageValue = $generalRule?->coverage_value ?? 80;

        // Map items to template rows
        $collection = $items->map(function ($item) use ($existingRules, $defaultCoverageType, $defaultCoverageValue) {
            $existingRule = $existingRules->get($item->code);

            // Map database 'fixed' to 'fixed_amount' for template
            $coverageType = $existingRule?->coverage_type ?? $defaultCoverageType;
            if ($coverageType === 'fixed') {
                $coverageType = 'fixed_amount';
            }

            return [
                'item_code' => $item->code,
                'item_name' => $item->name,
                'current_price' => number_format($item->price, 2, '.', ''),
                'coverage_type' => $coverageType,
                'coverage_value' => $existingRule?->coverage_value ?? $defaultCoverageValue,
                'notes' => $existingRule?->notes ?? '',
            ];
        });

        // Store row count for dropdown validation
        $this->rowCount = $collection->count();

        return $collection;
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
            'current_price',
            'coverage_type',
            'coverage_value',
            'notes',
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
            'B' => 40,  // item_name
            'C' => 15,  // current_price
            'D' => 18,  // coverage_type
            'E' => 18,  // coverage_value
            'F' => 30,  // notes
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Apply header styling
        $styles = [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
            ],
        ];

        if ($this->rowCount > 0) {
            $lastRow = $this->rowCount + 1; // +1 for header row

            // 1. Add dropdown validation to coverage_type column (column D)
            $this->addCoverageTypeDropdown($sheet, $lastRow);

            // 2. Add validation for coverage_value based on coverage_type
            $this->addCoverageValueValidation($sheet, $lastRow);

            // 3. Add conditional formatting to highlight potential errors
            $this->addErrorHighlighting($sheet, $lastRow);
        }

        return $styles;
    }

    /**
     * Add dropdown validation to coverage_type column
     */
    private function addCoverageTypeDropdown(Worksheet $sheet, int $lastRow): void
    {
        $coverageTypeColumn = 'D';

        for ($row = 2; $row <= $lastRow; $row++) {
            $cellCoordinate = $coverageTypeColumn.$row;
            $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Invalid Coverage Type');
            $validation->setError('Please select a valid coverage type from the dropdown.');
            $validation->setPromptTitle('Coverage Type');
            $validation->setPrompt('Select: percentage, fixed_amount, full, or excluded');
            $validation->setFormula1('"percentage,fixed_amount,full,excluded"');
        }
    }

    /**
     * Add validation for coverage_value based on coverage_type
     */
    private function addCoverageValueValidation(Worksheet $sheet, int $lastRow): void
    {
        $coverageValueColumn = 'E';

        for ($row = 2; $row <= $lastRow; $row++) {
            $cellCoordinate = $coverageValueColumn.$row;
            $validation = $sheet->getCell($cellCoordinate)->getDataValidation();
            $validation->setType(DataValidation::TYPE_DECIMAL);
            $validation->setErrorStyle(DataValidation::STYLE_WARNING);
            $validation->setOperator(DataValidation::OPERATOR_BETWEEN);
            $validation->setFormula1('0');
            $validation->setFormula2('999999');
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Check Coverage Value');
            $validation->setError('For percentage: use 0-100. For fixed_amount: use actual dollar amount (e.g., 5, 30). For full/excluded: value is ignored.');
            $validation->setPromptTitle('Coverage Value');
            $validation->setPrompt('Enter percentage (0-100) or fixed dollar amount');
        }
    }

    /**
     * Add conditional formatting to highlight potential errors
     */
    private function addErrorHighlighting(Worksheet $sheet, int $lastRow): void
    {
        // Highlight when percentage type has value > 100 (likely error)
        $this->addPercentageErrorHighlight($sheet, $lastRow);

        // Highlight when fixed_amount is > item price (likely error)
        $this->addFixedAmountErrorHighlight($sheet, $lastRow);

        // Highlight when fixed_amount has percentage-like value (e.g., 80 when price is 3)
        $this->addSuspiciousFixedAmountHighlight($sheet, $lastRow);
    }

    /**
     * Highlight percentage values > 100
     */
    private function addPercentageErrorHighlight(Worksheet $sheet, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $condition = new Conditional;
            $condition->setConditionType(Conditional::CONDITION_EXPRESSION);
            $condition->addCondition('AND($D'.$row.'="percentage",$E'.$row.'>100)');
            $condition->getStyle()->getFont()->getColor()->setARGB('FFFF0000');
            $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
            $condition->getStyle()->getFill()->getStartColor()->setARGB('FFFFC7CE');

            $conditionalStyles = $sheet->getStyle('E'.$row)->getConditionalStyles();
            $conditionalStyles[] = $condition;
            $sheet->getStyle('E'.$row)->setConditionalStyles($conditionalStyles);
        }
    }

    /**
     * Highlight fixed_amount > item price
     */
    private function addFixedAmountErrorHighlight(Worksheet $sheet, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $condition = new Conditional;
            $condition->setConditionType(Conditional::CONDITION_EXPRESSION);
            $condition->addCondition('AND($D'.$row.'="fixed_amount",$E'.$row.'>$C'.$row.')');
            $condition->getStyle()->getFont()->getColor()->setARGB('FFFF6600');
            $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
            $condition->getStyle()->getFill()->getStartColor()->setARGB('FFFFEB9C');

            $conditionalStyles = $sheet->getStyle('E'.$row)->getConditionalStyles();
            $conditionalStyles[] = $condition;
            $sheet->getStyle('E'.$row)->setConditionalStyles($conditionalStyles);
        }
    }

    /**
     * Highlight suspicious fixed_amount values (likely meant to be percentage)
     * Example: fixed_amount with value 80 when item price is 3
     */
    private function addSuspiciousFixedAmountHighlight(Worksheet $sheet, int $lastRow): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $condition = new Conditional;
            $condition->setConditionType(Conditional::CONDITION_EXPRESSION);
            // Flag if fixed_amount value is > 50 AND > 10x the item price
            $condition->addCondition('AND($D'.$row.'="fixed_amount",$E'.$row.'>50,$E'.$row.'>($C'.$row.'*10))');
            $condition->getStyle()->getFont()->getColor()->setARGB('FFFF6600');
            $condition->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
            $condition->getStyle()->getFill()->getStartColor()->setARGB('FFFFEB9C');

            $conditionalStyles = $sheet->getStyle('E'.$row)->getConditionalStyles();
            $conditionalStyles[] = $condition;
            $sheet->getStyle('E'.$row)->setConditionalStyles($conditionalStyles);
        }
    }
}
