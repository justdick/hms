<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PaymentAuditLog;
use App\Models\SystemConfiguration;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PdfService
{
    /**
     * Generate a patient statement PDF.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateStatement(Patient $patient, Carbon $startDate, Carbon $endDate)
    {
        $statementData = $this->getStatementData($patient, $startDate, $endDate);

        $pdf = Pdf::loadView('pdf.statement', $statementData);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Get statement data for a patient.
     */
    public function getStatementData(Patient $patient, Carbon $startDate, Carbon $endDate): array
    {
        // Get hospital information
        $hospital = [
            'name' => SystemConfiguration::get('hospital_name', 'Hospital Management System'),
            'address' => SystemConfiguration::get('hospital_address', ''),
            'phone' => SystemConfiguration::get('hospital_phone', ''),
            'email' => SystemConfiguration::get('hospital_email', ''),
            'logo' => SystemConfiguration::get('hospital_logo', ''),
        ];

        // Get patient details
        $patientDetails = [
            'id' => $patient->id,
            'patient_number' => $patient->patient_number,
            'name' => $patient->full_name,
            'date_of_birth' => $patient->date_of_birth?->format('M j, Y'),
            'gender' => $patient->gender,
            'phone_number' => $patient->phone_number,
            'address' => $patient->address,
        ];

        // Get all charges for the patient within the date range
        $charges = $this->getPatientCharges($patient, $startDate, $endDate);

        // Get all payments for the patient within the date range
        $payments = $this->getPatientPayments($patient, $startDate, $endDate);

        // Calculate totals
        $totalCharges = $charges->sum('amount');
        $totalPaid = $payments->sum('paid_amount');
        $totalInsuranceCovered = $charges->sum('insurance_covered_amount');
        $balance = $totalCharges - $totalPaid - $totalInsuranceCovered;

        // Get opening balance (charges before start date minus payments before start date)
        $openingBalance = $this->calculateOpeningBalance($patient, $startDate);

        return [
            'hospital' => $hospital,
            'patient' => $patientDetails,
            'statement_period' => [
                'start_date' => $startDate->format('M j, Y'),
                'end_date' => $endDate->format('M j, Y'),
            ],
            'generated_at' => now()->format('M j, Y g:i A'),
            'charges' => $charges,
            'payments' => $payments,
            'summary' => [
                'opening_balance' => $openingBalance,
                'total_charges' => $totalCharges,
                'total_paid' => $totalPaid,
                'total_insurance_covered' => $totalInsuranceCovered,
                'closing_balance' => $openingBalance + $balance,
            ],
        ];
    }

    /**
     * Get patient charges within a date range.
     */
    protected function getPatientCharges(Patient $patient, Carbon $startDate, Carbon $endDate): Collection
    {
        return Charge::whereHas('patientCheckin', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id);
        })
            ->whereBetween('charged_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereNotIn('status', ['voided', 'cancelled'])
            ->orderBy('charged_at')
            ->get()
            ->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'date' => $charge->charged_at?->format('M j, Y'),
                    'description' => $charge->description,
                    'service_type' => $charge->service_type,
                    'amount' => (float) $charge->amount,
                    'insurance_covered_amount' => (float) $charge->insurance_covered_amount,
                    'patient_copay_amount' => (float) $charge->patient_copay_amount,
                    'status' => $charge->status,
                ];
            });
    }

    /**
     * Get patient payments within a date range.
     */
    protected function getPatientPayments(Patient $patient, Carbon $startDate, Carbon $endDate): Collection
    {
        return Charge::whereHas('patientCheckin', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id);
        })
            ->whereIn('status', ['paid', 'partial'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderBy('paid_at')
            ->get()
            ->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'date' => $charge->paid_at?->format('M j, Y'),
                    'receipt_number' => $charge->receipt_number,
                    'description' => $charge->description,
                    'paid_amount' => (float) $charge->paid_amount,
                    'payment_method' => $charge->metadata['payment_method'] ?? 'Unknown',
                ];
            });
    }

    /**
     * Calculate opening balance for a patient before a given date.
     */
    protected function calculateOpeningBalance(Patient $patient, Carbon $beforeDate): float
    {
        // Total charges before the start date
        $totalChargesBefore = Charge::whereHas('patientCheckin', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id);
        })
            ->where('charged_at', '<', $beforeDate->startOfDay())
            ->whereNotIn('status', ['voided', 'cancelled'])
            ->sum('amount');

        // Total payments before the start date
        $totalPaymentsBefore = Charge::whereHas('patientCheckin', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id);
        })
            ->whereIn('status', ['paid', 'partial'])
            ->whereNotNull('paid_at')
            ->where('paid_at', '<', $beforeDate->startOfDay())
            ->sum('paid_amount');

        // Total insurance covered before the start date
        $totalInsuranceBefore = Charge::whereHas('patientCheckin', function ($query) use ($patient) {
            $query->where('patient_id', $patient->id);
        })
            ->where('charged_at', '<', $beforeDate->startOfDay())
            ->whereNotIn('status', ['voided', 'cancelled'])
            ->sum('insurance_covered_amount');

        return $totalChargesBefore - $totalPaymentsBefore - $totalInsuranceBefore;
    }

    /**
     * Log statement generation in audit trail.
     */
    public function logStatementGeneration(Patient $patient, Carbon $startDate, Carbon $endDate, int $userId, ?string $ipAddress = null): void
    {
        PaymentAuditLog::create([
            'patient_id' => $patient->id,
            'user_id' => $userId,
            'action' => PaymentAuditLog::ACTION_STATEMENT_GENERATED,
            'new_values' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'generated_at' => now()->toIso8601String(),
            ],
            'ip_address' => $ipAddress,
        ]);
    }

    /**
     * Generate outstanding balances report PDF.
     */
    public function generateOutstandingReport(Collection $balances, array $summary)
    {
        // Get hospital information
        $hospital = [
            'name' => SystemConfiguration::get('hospital_name', 'Hospital Management System'),
            'address' => SystemConfiguration::get('hospital_address', ''),
            'phone' => SystemConfiguration::get('hospital_phone', ''),
        ];

        $data = [
            'hospital' => $hospital,
            'generated_at' => now()->format('M j, Y g:i A'),
            'balances' => $balances,
            'summary' => $summary,
        ];

        $pdf = Pdf::loadView('pdf.outstanding-report', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->output();
    }

    /**
     * Validate statement data completeness.
     */
    public function validateStatementData(array $data): bool
    {
        $requiredSections = [
            'hospital',
            'patient',
            'statement_period',
            'generated_at',
            'charges',
            'payments',
            'summary',
        ];

        foreach ($requiredSections as $section) {
            if (! array_key_exists($section, $data)) {
                return false;
            }
        }

        // Validate hospital section
        $requiredHospitalFields = ['name'];
        foreach ($requiredHospitalFields as $field) {
            if (! isset($data['hospital'][$field])) {
                return false;
            }
        }

        // Validate patient section
        $requiredPatientFields = ['patient_number', 'name'];
        foreach ($requiredPatientFields as $field) {
            if (! isset($data['patient'][$field])) {
                return false;
            }
        }

        // Validate statement period
        if (! isset($data['statement_period']['start_date']) || ! isset($data['statement_period']['end_date'])) {
            return false;
        }

        // Validate summary section
        $requiredSummaryFields = ['opening_balance', 'total_charges', 'total_paid', 'closing_balance'];
        foreach ($requiredSummaryFields as $field) {
            if (! array_key_exists($field, $data['summary'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate revenue report PDF.
     */
    public function generateRevenueReport(array $report, string $groupBy)
    {
        // Get hospital information
        $hospital = [
            'name' => SystemConfiguration::get('hospital_name', 'Hospital Management System'),
            'address' => SystemConfiguration::get('hospital_address', ''),
            'phone' => SystemConfiguration::get('hospital_phone', ''),
        ];

        $groupByLabel = match ($groupBy) {
            'date' => 'Date',
            'department' => 'Department',
            'service_type' => 'Service Type',
            'payment_method' => 'Payment Method',
            'cashier' => 'Cashier',
            default => 'Category',
        };

        $data = [
            'hospital' => $hospital,
            'generated_at' => now()->format('M j, Y g:i A'),
            'report' => $report,
            'group_by' => $groupBy,
            'group_by_label' => $groupByLabel,
        ];

        $pdf = Pdf::loadView('pdf.revenue-report', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
