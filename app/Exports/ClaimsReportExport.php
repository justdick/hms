<?php

namespace App\Exports;

use App\Models\InsuranceClaim;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class ClaimsReportExport implements WithMultipleSheets
{
    public function __construct(
        protected string $reportType,
        protected ?string $dateFrom = null,
        protected ?string $dateTo = null,
        protected ?int $providerId = null
    ) {}

    public function sheets(): array
    {
        return match ($this->reportType) {
            'summary' => [new ClaimsSummarySheet($this->dateFrom, $this->dateTo, $this->providerId)],
            'outstanding' => [new OutstandingClaimsSheet($this->providerId)],
            'rejections' => [new RejectionAnalysisSheet($this->dateFrom, $this->dateTo, $this->providerId)],
            'tariff-coverage' => [new TariffCoverageSheet],
            default => [new ClaimsSummarySheet($this->dateFrom, $this->dateTo, $this->providerId)],
        };
    }
}

class ClaimsSummarySheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected ?string $dateFrom = null,
        protected ?string $dateTo = null,
        protected ?int $providerId = null
    ) {
        $this->dateFrom = $dateFrom ?? now()->startOfMonth()->toDateString();
        $this->dateTo = $dateTo ?? now()->endOfMonth()->toDateString();
    }

    public function title(): string
    {
        return 'Claims Summary';
    }

    public function collection(): Collection
    {
        $query = InsuranceClaim::with(['patientInsurance.plan.provider', 'patient'])
            ->whereBetween('date_of_attendance', [$this->dateFrom, $this->dateTo]);

        if ($this->providerId) {
            $query->whereHas('patientInsurance.plan', function ($q) {
                $q->where('insurance_provider_id', $this->providerId);
            });
        }

        return $query->orderBy('date_of_attendance', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Claim Check Code',
            'Patient Name',
            'Folder ID',
            'Provider',
            'Plan',
            'Date of Attendance',
            'Type of Service',
            'Status',
            'Total Claimed (GHS)',
            'Approved Amount (GHS)',
            'Paid Amount (GHS)',
            'Outstanding (GHS)',
            'Vetted By',
            'Vetted At',
            'Submitted At',
        ];
    }

    /**
     * @param  InsuranceClaim  $claim
     */
    public function map($claim): array
    {
        $approved = $claim->approved_amount ?? 0;
        $paid = $claim->payment_amount ?? 0;

        return [
            $claim->claim_check_code,
            $claim->patient_surname.' '.$claim->patient_other_names,
            $claim->folder_id,
            $claim->patientInsurance?->plan?->provider?->name ?? 'N/A',
            $claim->patientInsurance?->plan?->plan_name ?? 'N/A',
            $claim->date_of_attendance?->format('Y-m-d'),
            $claim->type_of_service,
            ucfirst($claim->status),
            number_format($claim->total_claim_amount, 2),
            number_format($approved, 2),
            number_format($paid, 2),
            number_format($approved - $paid, 2),
            $claim->vettedBy?->name ?? 'N/A',
            $claim->vetted_at?->format('Y-m-d H:i'),
            $claim->submitted_at?->format('Y-m-d H:i'),
        ];
    }
}

class OutstandingClaimsSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected ?int $providerId = null) {}

    public function title(): string
    {
        return 'Outstanding Claims';
    }

    public function collection(): Collection
    {
        $query = InsuranceClaim::with(['patientInsurance.plan.provider', 'patient'])
            ->whereIn('status', ['submitted', 'approved']);

        if ($this->providerId) {
            $query->whereHas('patientInsurance.plan', function ($q) {
                $q->where('insurance_provider_id', $this->providerId);
            });
        }

        return $query->orderBy('submitted_at', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'Claim Check Code',
            'Patient Name',
            'Folder ID',
            'Provider',
            'Plan',
            'Date of Attendance',
            'Status',
            'Total Claimed (GHS)',
            'Approved Amount (GHS)',
            'Outstanding (GHS)',
            'Days Outstanding',
            'Submitted At',
            'Aging Bucket',
        ];
    }

    /**
     * @param  InsuranceClaim  $claim
     */
    public function map($claim): array
    {
        $approved = $claim->approved_amount ?? $claim->total_claim_amount;
        $paid = $claim->payment_amount ?? 0;
        $outstanding = $approved - $paid;
        $daysOutstanding = now()->diffInDays($claim->submitted_at ?? $claim->created_at);

        $agingBucket = match (true) {
            $daysOutstanding <= 30 => '0-30 days',
            $daysOutstanding <= 60 => '31-60 days',
            $daysOutstanding <= 90 => '61-90 days',
            default => '90+ days',
        };

        return [
            $claim->claim_check_code,
            $claim->patient_surname.' '.$claim->patient_other_names,
            $claim->folder_id,
            $claim->patientInsurance?->plan?->provider?->name ?? 'N/A',
            $claim->patientInsurance?->plan?->plan_name ?? 'N/A',
            $claim->date_of_attendance?->format('Y-m-d'),
            ucfirst($claim->status),
            number_format($claim->total_claim_amount, 2),
            number_format($approved, 2),
            number_format($outstanding, 2),
            $daysOutstanding,
            $claim->submitted_at?->format('Y-m-d H:i'),
            $agingBucket,
        ];
    }
}

class RejectionAnalysisSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected ?string $dateFrom = null,
        protected ?string $dateTo = null,
        protected ?int $providerId = null
    ) {
        $this->dateFrom = $dateFrom ?? now()->startOfMonth()->toDateString();
        $this->dateTo = $dateTo ?? now()->endOfMonth()->toDateString();
    }

    public function title(): string
    {
        return 'Rejection Analysis';
    }

    public function collection(): Collection
    {
        $query = InsuranceClaim::with(['patientInsurance.plan.provider', 'patient'])
            ->where('status', 'rejected')
            ->whereBetween('updated_at', [$this->dateFrom, $this->dateTo]);

        if ($this->providerId) {
            $query->whereHas('patientInsurance.plan', function ($q) {
                $q->where('insurance_provider_id', $this->providerId);
            });
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Claim Check Code',
            'Patient Name',
            'Folder ID',
            'Provider',
            'Plan',
            'Date of Attendance',
            'Total Claimed (GHS)',
            'Rejection Reason',
            'Rejected At',
            'Resubmission Count',
        ];
    }

    /**
     * @param  InsuranceClaim  $claim
     */
    public function map($claim): array
    {
        return [
            $claim->claim_check_code,
            $claim->patient_surname.' '.$claim->patient_other_names,
            $claim->folder_id,
            $claim->patientInsurance?->plan?->provider?->name ?? 'N/A',
            $claim->patientInsurance?->plan?->plan_name ?? 'N/A',
            $claim->date_of_attendance?->format('Y-m-d'),
            number_format($claim->total_claim_amount, 2),
            $claim->rejection_reason ?? 'Not specified',
            $claim->rejected_at?->format('Y-m-d H:i') ?? $claim->updated_at?->format('Y-m-d H:i'),
            $claim->resubmission_count ?? 0,
        ];
    }
}

class TariffCoverageSheet implements FromCollection, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Tariff Coverage';
    }

    public function collection(): Collection
    {
        $coverage = [];

        // Drugs coverage
        $totalDrugs = \App\Models\Drug::active()->count();
        $mappedDrugs = \App\Models\NhisItemMapping::where('item_type', 'drug')->count();
        $coverage[] = [
            'type' => 'Drugs',
            'total' => $totalDrugs,
            'mapped' => $mappedDrugs,
            'unmapped' => $totalDrugs - $mappedDrugs,
            'percentage' => $totalDrugs > 0 ? round(($mappedDrugs / $totalDrugs) * 100, 2) : 0,
        ];

        // Lab services coverage
        $totalLabs = \App\Models\LabService::active()->count();
        $mappedLabs = \App\Models\NhisItemMapping::where('item_type', 'lab_service')->count();
        $coverage[] = [
            'type' => 'Lab Services',
            'total' => $totalLabs,
            'mapped' => $mappedLabs,
            'unmapped' => $totalLabs - $mappedLabs,
            'percentage' => $totalLabs > 0 ? round(($mappedLabs / $totalLabs) * 100, 2) : 0,
        ];

        // Procedures coverage
        $totalProcedures = \App\Models\MinorProcedureType::active()->count();
        $mappedProcedures = \App\Models\NhisItemMapping::where('item_type', 'procedure')->count();
        $coverage[] = [
            'type' => 'Procedures',
            'total' => $totalProcedures,
            'mapped' => $mappedProcedures,
            'unmapped' => $totalProcedures - $mappedProcedures,
            'percentage' => $totalProcedures > 0 ? round(($mappedProcedures / $totalProcedures) * 100, 2) : 0,
        ];

        // Overall totals
        $totalItems = $totalDrugs + $totalLabs + $totalProcedures;
        $totalMapped = $mappedDrugs + $mappedLabs + $mappedProcedures;
        $coverage[] = [
            'type' => 'TOTAL',
            'total' => $totalItems,
            'mapped' => $totalMapped,
            'unmapped' => $totalItems - $totalMapped,
            'percentage' => $totalItems > 0 ? round(($totalMapped / $totalItems) * 100, 2) : 0,
        ];

        return collect($coverage);
    }

    public function headings(): array
    {
        return [
            'Item Type',
            'Total Items',
            'Mapped to NHIS',
            'Unmapped',
            'Coverage %',
        ];
    }
}
