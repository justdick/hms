<?php

namespace App\Exports;

use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Excel export for a claim batch.
 * Exports the SAME data structure as the XML export for NHIS submission.
 * 
 * Contains three sheets:
 * 1. Claims - main claim data (matches XML claim elements)
 * 2. Diagnoses - all diagnoses with ICD-10 and G-DRG codes
 * 3. Medicines - all medicines with prescription details
 */
class ClaimBatchExcelExport implements WithMultipleSheets
{
    public function __construct(
        protected ClaimBatch $batch
    ) {
    }

    public function sheets(): array
    {
        return [
            new BatchClaimsExcelSheet($this->batch),
            new BatchDiagnosesExcelSheet($this->batch),
            new BatchMedicinesExcelSheet($this->batch),
        ];
    }
}

/**
 * Sheet 1: Claims (matches XML claim elements structure)
 */
class BatchClaimsExcelSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected ClaimBatch $batch)
    {
    }

    public function title(): string
    {
        return 'Claims';
    }

    public function collection(): Collection
    {
        return $this->batch->batchItems
            ->map(fn($item) => $item->insuranceClaim)
            ->filter();
    }

    public function headings(): array
    {
        return [
            'claimID',
            'claimCheckCode',
            'memberNo',
            'cardSerialNo',
            'surname',
            'otherNames',
            'dateOfBirth',
            'gender',
            'hospitalRecNo',
            'isDependant',
            'typeOfService',
            'isUnbundled',
            'includesPharmacy',
            'typeOfAttendance',
            'serviceOutcome',
            'dateOfService',
            'dischargeDate',
            'specialtyAttended',
        ];
    }

    public function map($claim): array
    {
        $hasPharmacy = $claim->items?->where('item_type', 'medication')->isNotEmpty() ?? false;

        return [
            $claim->id,
            $claim->claim_check_code ?? '',
            $claim->membership_id ?? $claim->patientInsurance?->membership_id ?? '',
            '', // cardSerialNo
            $claim->patient_surname ?? '',
            $claim->patient_other_names ?? '',
            $claim->patient_dob?->format('Y-m-d') ?? '',
            $this->formatGender($claim->patient_gender),
            $claim->folder_id ?? '',
            '0', // isDependant
            $this->mapToNhisServiceCode($claim->type_of_service),
            $claim->is_unbundled ? '1' : '0',
            $hasPharmacy ? '1' : '0',
            $this->mapToNhisAttendanceCode($claim->type_of_attendance),
            'DISC', // serviceOutcome
            $claim->date_of_attendance?->format('Y-m-d') ?? '',
            $claim->date_of_discharge?->format('Y-m-d') ?? $claim->date_of_attendance?->format('Y-m-d') ?? '',
            $claim->specialty_attended ?? 'OPDC',
        ];
    }

    protected function formatGender(?string $gender): string
    {
        if (!$gender)
            return '';
        $gender = strtolower($gender);
        if (in_array($gender, ['m', 'male']))
            return 'M';
        if (in_array($gender, ['f', 'female']))
            return 'F';
        return strtoupper(substr($gender, 0, 1));
    }

    protected function mapToNhisServiceCode(?string $value): string
    {
        return match (strtolower($value ?? '')) {
            'outpatient', 'opd' => '1',
            'inpatient', 'ipd' => '2',
            '1' => '1',
            '2' => '2',
            default => '1',
        };
    }

    protected function mapToNhisAttendanceCode(?string $value): string
    {
        return match (strtolower($value ?? '')) {
            'routine', 'new' => '1',
            'referral' => '2',
            'emergency' => '3',
            'follow-up', 'follow_up', 'followup' => '4',
            '1', '2', '3', '4' => $value,
            default => '1',
        };
    }
}

/**
 * Sheet 2: Diagnoses (matches XML diagnosis elements)
 */
class BatchDiagnosesExcelSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected ClaimBatch $batch)
    {
    }

    public function title(): string
    {
        return 'Diagnoses';
    }

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->batch->batchItems as $batchItem) {
            $claim = $batchItem->insuranceClaim;
            if (!$claim)
                continue;

            $gdrgCode = $claim->gdrgTariff?->code ?? $claim->c_drg_code ?? '';
            $serviceDate = $claim->date_of_attendance?->format('Y-m-d') ?? '';

            $claimDiagnoses = $claim->claimDiagnoses ?? collect();

            if ($claimDiagnoses->isNotEmpty()) {
                foreach ($claimDiagnoses as $diagnosis) {
                    $rows->push([
                        'claimCheckCode' => $claim->claim_check_code ?? '',
                        'serviceDate' => $serviceDate,
                        'gdrgCode' => $gdrgCode,
                        'ICD10' => $diagnosis->diagnosis?->code ?? $diagnosis->icd_code ?? '-',
                        'diagnosis' => $diagnosis->diagnosis?->name ?? $diagnosis->description ?? '',
                        'isPrimary' => $diagnosis->is_primary ? 'Yes' : 'No',
                    ]);
                }
            } elseif ($claim->primary_diagnosis_code || $claim->primary_diagnosis_description) {
                $rows->push([
                    'claimCheckCode' => $claim->claim_check_code ?? '',
                    'serviceDate' => $serviceDate,
                    'gdrgCode' => $gdrgCode,
                    'ICD10' => $claim->primary_diagnosis_code ?? '-',
                    'diagnosis' => $claim->primary_diagnosis_description ?? '',
                    'isPrimary' => 'Yes',
                ]);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'claimCheckCode',
            'serviceDate',
            'gdrgCode',
            'ICD10',
            'diagnosis',
            'isPrimary',
        ];
    }
}

/**
 * Sheet 3: Medicines (matches XML medicine elements)
 */
class BatchMedicinesExcelSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected ClaimBatch $batch)
    {
    }

    public function title(): string
    {
        return 'Medicines';
    }

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->batch->batchItems as $batchItem) {
            $claim = $batchItem->insuranceClaim;
            if (!$claim)
                continue;

            $items = $claim->items?->where('item_type', 'medication') ?? collect();

            foreach ($items as $item) {
                $prescription = $item->charge?->prescription;
                $dispensedQty = $prescription?->quantity_to_dispense ?? $prescription?->quantity ?? $item->quantity ?? 1;
                $nhisCode = $item->nhis_code ?? $item->nhisTariff?->nhis_code ?? $item->code ?? '';

                // Build unparsed prescription string
                $unparsed = $this->buildPrescriptionUnparsed($prescription);

                $rows->push([
                    'claimCheckCode' => $claim->claim_check_code ?? '',
                    'medicineCode' => $nhisCode,
                    'dispensedQty' => $dispensedQty,
                    'serviceDate' => $item->item_date?->format('Y-m-d') ?? $claim->date_of_attendance?->format('Y-m-d') ?? '',
                    'dose' => '',
                    'frequency' => '',
                    'duration' => '',
                    'prescriptionUnparsed' => $unparsed,
                ]);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'claimCheckCode',
            'medicineCode',
            'dispensedQty',
            'serviceDate',
            'dose',
            'frequency',
            'duration',
            'prescriptionUnparsed',
        ];
    }

    protected function buildPrescriptionUnparsed($prescription): string
    {
        if (!$prescription)
            return '';

        $parts = [];

        $dose = $prescription->dosage ?? $prescription->dose ?? '';
        if ($dose)
            $parts[] = $dose;

        $frequency = $prescription->frequency ?? '';
        if ($frequency)
            $parts[] = strtoupper($frequency);

        $duration = $prescription->duration ?? '';
        $durationUnit = $prescription->duration_unit ?? 'DAYS';
        if ($duration) {
            $parts[] = 'X';
            $parts[] = $duration . strtoupper($durationUnit);
        }

        return implode(' ', $parts);
    }
}
