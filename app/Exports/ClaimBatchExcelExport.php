<?php

namespace App\Exports;

use App\Models\ClaimBatch;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Simple Excel export for a claim batch.
 *
 * Columns: ID | Patient Name | Member NO | OPD NO | Diagnosis | Others
 */
class ClaimBatchExcelExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    protected int $rowNumber = 0;

    public function __construct(
        protected ClaimBatch $batch
    ) {}

    public function title(): string
    {
        return 'Claims';
    }

    public function collection(): Collection
    {
        return $this->batch->batchItems
            ->map(fn ($item) => $item->insuranceClaim)
            ->filter();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Patient Name',
            'Member NO',
            'OPD NO',
            'Diagnosis',
            'Others',
        ];
    }

    public function map($claim): array
    {
        $this->rowNumber++;

        $patientName = trim(($claim->patient_surname ?? '').' '.($claim->patient_other_names ?? ''));

        $diagnoses = $claim->claimDiagnoses
            ->map(fn ($d) => $d->diagnosis?->diagnosis ?? '')
            ->filter()
            ->implode(', ');

        if (! $diagnoses && $claim->primary_diagnosis_description) {
            $diagnoses = $claim->primary_diagnosis_description;
        }

        return [
            $this->rowNumber,
            $patientName,
            $claim->membership_id ?? '',
            $claim->folder_id ?? '',
            $diagnoses,
            '', // Others - blank column for manual notes
        ];
    }
}
