<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PaymentAuditLog;
use App\Models\SystemConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    /**
     * Generate a unique receipt number.
     * Format: RCP-YYYYMMDD-NNNN
     */
    public function generateReceiptNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "RCP-{$date}-";

        // Use database lock to ensure uniqueness
        return DB::transaction(function () use ($prefix) {
            // Get the last receipt number for today
            $lastReceipt = Charge::whereDate('paid_at', today())
                ->whereNotNull('receipt_number')
                ->where('receipt_number', 'LIKE', $prefix.'%')
                ->orderByRaw('CAST(SUBSTRING(receipt_number, -4) AS UNSIGNED) DESC')
                ->lockForUpdate()
                ->first();

            if ($lastReceipt && $lastReceipt->receipt_number) {
                $lastNumber = (int) substr($lastReceipt->receipt_number, -4);
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Validate receipt number format.
     */
    public function isValidReceiptNumber(string $receiptNumber): bool
    {
        return (bool) preg_match('/^RCP-\d{8}-\d{4}$/', $receiptNumber);
    }

    /**
     * Check if receipt number is unique.
     */
    public function isUniqueReceiptNumber(string $receiptNumber): bool
    {
        return ! Charge::where('receipt_number', $receiptNumber)->exists();
    }

    /**
     * Get receipt data for a charge.
     */
    public function getReceiptData(Charge $charge): array
    {
        $charge->load([
            'patientCheckin.patient',
            'patientCheckin.department',
            'processedByUser',
        ]);

        $hospitalName = SystemConfiguration::get('hospital_name', 'Hospital Management System');
        $hospitalAddress = SystemConfiguration::get('hospital_address', '');
        $hospitalPhone = SystemConfiguration::get('hospital_phone', '');

        return [
            'receipt_number' => $charge->receipt_number,
            'hospital' => [
                'name' => $hospitalName,
                'address' => $hospitalAddress,
                'phone' => $hospitalPhone,
            ],
            'date' => $charge->paid_at?->format('M j, Y'),
            'time' => $charge->paid_at?->format('g:i A'),
            'datetime' => $charge->paid_at?->format('M j, Y g:i A'),
            'patient' => [
                'name' => $charge->patientCheckin?->patient
                    ? $charge->patientCheckin->patient->first_name.' '.$charge->patientCheckin->patient->last_name
                    : 'Unknown',
                'patient_number' => $charge->patientCheckin?->patient?->patient_number ?? 'N/A',
            ],
            'charge' => [
                'id' => $charge->id,
                'description' => $charge->description,
                'service_type' => $charge->service_type,
                'amount' => $charge->amount,
                'paid_amount' => $charge->paid_amount,
                'is_insurance_claim' => $charge->is_insurance_claim,
                'insurance_covered_amount' => $charge->insurance_covered_amount,
                'patient_copay_amount' => $charge->patient_copay_amount,
            ],
            'cashier' => [
                'name' => $charge->processedByUser?->name ?? 'System',
            ],
        ];
    }

    /**
     * Get receipt data for multiple charges (grouped payment).
     */
    public function getGroupedReceiptData(array $chargeIds, string $receiptNumber): array
    {
        $charges = Charge::whereIn('id', $chargeIds)
            ->with([
                'patientCheckin.patient',
                'patientCheckin.department',
                'processedByUser',
            ])
            ->get();

        if ($charges->isEmpty()) {
            return [];
        }

        $firstCharge = $charges->first();
        $hospitalName = SystemConfiguration::get('hospital_name', 'Hospital Management System');
        $hospitalAddress = SystemConfiguration::get('hospital_address', '');
        $hospitalPhone = SystemConfiguration::get('hospital_phone', '');

        $totalAmount = $charges->sum('amount');
        $totalPaid = $charges->sum('paid_amount');
        $totalInsuranceCovered = $charges->sum('insurance_covered_amount');
        $totalPatientCopay = $charges->sum('patient_copay_amount');

        return [
            'receipt_number' => $receiptNumber,
            'hospital' => [
                'name' => $hospitalName,
                'address' => $hospitalAddress,
                'phone' => $hospitalPhone,
            ],
            'date' => now()->format('M j, Y'),
            'time' => now()->format('g:i A'),
            'datetime' => now()->format('M j, Y g:i A'),
            'patient' => [
                'name' => $firstCharge->patientCheckin?->patient
                    ? $firstCharge->patientCheckin->patient->first_name.' '.$firstCharge->patientCheckin->patient->last_name
                    : 'Unknown',
                'patient_number' => $firstCharge->patientCheckin?->patient?->patient_number ?? 'N/A',
            ],
            'charges' => $charges->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'description' => $charge->description,
                    'service_type' => $charge->service_type,
                    'amount' => $charge->amount,
                    'paid_amount' => $charge->paid_amount,
                    'is_insurance_claim' => $charge->is_insurance_claim,
                    'insurance_covered_amount' => $charge->insurance_covered_amount,
                    'patient_copay_amount' => $charge->patient_copay_amount,
                ];
            })->toArray(),
            'totals' => [
                'amount' => $totalAmount,
                'paid' => $totalPaid,
                'insurance_covered' => $totalInsuranceCovered,
                'patient_copay' => $totalPatientCopay,
            ],
            'cashier' => [
                'name' => $firstCharge->processedByUser?->name ?? auth()->user()?->name ?? 'System',
            ],
        ];
    }

    /**
     * Assign receipt number to charges and log the action.
     */
    public function assignReceiptNumber(array $chargeIds, User $user, ?string $ipAddress = null): string
    {
        $receiptNumber = $this->generateReceiptNumber();

        DB::transaction(function () use ($chargeIds, $receiptNumber, $user) {
            Charge::whereIn('id', $chargeIds)->update([
                'receipt_number' => $receiptNumber,
                'processed_by' => $user->id,
            ]);
        });

        return $receiptNumber;
    }

    /**
     * Log receipt print action.
     */
    public function logReceiptPrint(array $chargeIds, User $user, string $receiptNumber, ?string $ipAddress = null): void
    {
        foreach ($chargeIds as $chargeId) {
            $charge = Charge::find($chargeId);
            if ($charge) {
                PaymentAuditLog::create([
                    'charge_id' => $charge->id,
                    'patient_id' => $charge->patientCheckin?->patient_id,
                    'user_id' => $user->id,
                    'action' => PaymentAuditLog::ACTION_RECEIPT_PRINTED,
                    'new_values' => [
                        'receipt_number' => $receiptNumber,
                        'printed_at' => now()->toIso8601String(),
                    ],
                    'ip_address' => $ipAddress,
                ]);
            }
        }
    }
}
