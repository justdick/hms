<?php

namespace App\Exports;

use App\Models\InsuranceCoverageRule;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CoverageRulesWithHistoryExport implements WithMultipleSheets
{
    public function __construct(
        protected int $planId,
        protected bool $includeHistory = false
    ) {}

    public function sheets(): array
    {
        $sheets = [
            new CoverageRulesSheet($this->planId),
        ];

        if ($this->includeHistory) {
            $sheets[] = new CoverageRulesHistorySheet($this->planId);
        }

        return $sheets;
    }
}

class CoverageRulesSheet implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected int $planId) {}

    public function collection()
    {
        return InsuranceCoverageRule::where('insurance_plan_id', $this->planId)
            ->orderBy('coverage_category')
            ->orderBy('item_code')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Category',
            'Item Code',
            'Item Description',
            'Coverage Type',
            'Coverage Value',
            'Patient Copay %',
            'Is Covered',
            'Is Active',
            'Max Qty Per Visit',
            'Max Amount Per Visit',
            'Requires Preauth',
            'Effective From',
            'Effective To',
            'Notes',
            'Created At',
            'Updated At',
        ];
    }

    public function map($rule): array
    {
        return [
            $rule->id,
            $rule->coverage_category,
            $rule->item_code ?? 'DEFAULT',
            $rule->item_description,
            $rule->coverage_type,
            $rule->coverage_value,
            $rule->patient_copay_percentage,
            $rule->is_covered ? 'Yes' : 'No',
            $rule->is_active ? 'Yes' : 'No',
            $rule->max_quantity_per_visit,
            $rule->max_amount_per_visit,
            $rule->requires_preauthorization ? 'Yes' : 'No',
            $rule->effective_from?->format('Y-m-d'),
            $rule->effective_to?->format('Y-m-d'),
            $rule->notes,
            $rule->created_at->format('Y-m-d H:i:s'),
            $rule->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}

class CoverageRulesHistorySheet implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(protected int $planId) {}

    public function collection()
    {
        return \App\Models\InsuranceCoverageRuleHistory::whereHas('rule', function ($query) {
            $query->where('insurance_plan_id', $this->planId);
        })
            ->orWhereNull('insurance_coverage_rule_id')
            ->with(['rule', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'History ID',
            'Rule ID',
            'Category',
            'Item Code',
            'Action',
            'Changed By',
            'Field Changed',
            'Old Value',
            'New Value',
            'Batch ID',
            'Changed At',
        ];
    }

    public function map($history): array
    {
        $rows = [];
        $changedFields = $history->changed_fields;

        if (empty($changedFields)) {
            // For created/deleted actions with no field changes
            $rows[] = [
                $history->id,
                $history->insurance_coverage_rule_id,
                $history->rule?->coverage_category ?? 'N/A',
                $history->rule?->item_code ?? ($history->old_values['item_code'] ?? 'DEFAULT'),
                $history->action,
                $history->user?->name ?? 'System',
                'N/A',
                'N/A',
                'N/A',
                $history->batch_id,
                $history->created_at->format('Y-m-d H:i:s'),
            ];
        } else {
            // Create a row for each changed field
            foreach ($changedFields as $field => $values) {
                $rows[] = [
                    $history->id,
                    $history->insurance_coverage_rule_id,
                    $history->rule?->coverage_category ?? 'N/A',
                    $history->rule?->item_code ?? 'DEFAULT',
                    $history->action,
                    $history->user?->name ?? 'System',
                    $field,
                    $this->formatValue($values['old']),
                    $this->formatValue($values['new']),
                    $history->batch_id,
                    $history->created_at->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $rows;
    }

    protected function formatValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
