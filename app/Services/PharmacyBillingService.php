<?php

namespace App\Services;

use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\Prescription;
use Illuminate\Support\Facades\Auth;

class PharmacyBillingService
{
    /**
     * Create a charge for a prescription when it's created by the doctor.
     */
    public function createChargeForPrescription(Prescription $prescription): Charge
    {
        $drug = $prescription->drug;
        $quantity = $prescription->quantity;

        // Calculate amount: unit price × quantity
        $amount = $drug->unit_price * $quantity;

        $user = Auth::user();

        // Get patient_checkin_id based on prescription type
        $patientCheckinId = $this->getPatientCheckinId($prescription);

        return Charge::create([
            'patient_checkin_id' => $patientCheckinId,
            'prescription_id' => $prescription->id,
            'service_type' => 'pharmacy',
            'service_code' => $drug->code,
            'description' => "{$drug->name} ({$quantity} {$drug->dosage_form})",
            'amount' => $amount,
            'charge_type' => 'medication',
            'status' => 'pending',
            'charged_at' => now(),
            'created_by_type' => $user ? get_class($user) : null,
            'created_by_id' => $user?->id,
            'notes' => "Auto-generated from prescription #{$prescription->id}",
        ]);
    }

    /**
     * Get patient_checkin_id from prescription, handling both consultation and ward round prescriptions.
     */
    protected function getPatientCheckinId(Prescription $prescription): ?int
    {
        // If prescription has a direct consultation relationship
        if ($prescription->consultation_id && $prescription->consultation) {
            return $prescription->consultation->patient_checkin_id;
        }

        // If prescription belongs to a ward round (polymorphic relationship)
        if ($prescription->prescribable_type === 'App\Models\WardRound' && $prescription->prescribable) {
            return $prescription->prescribable->patientAdmission?->consultation?->patient_checkin_id;
        }

        return null;
    }

    /**
     * Update charge when prescription quantity is adjusted during pharmacy review.
     */
    public function updateChargeForReview(Prescription $prescription, int $newQuantity, ?string $reason = null): Charge
    {
        $charge = $prescription->charge;

        if (! $charge) {
            throw new \Exception('No charge found for prescription');
        }

        $drug = $prescription->drug;
        $newAmount = $drug->unit_price * $newQuantity;

        $charge->update([
            'amount' => $newAmount,
            'description' => "{$drug->name} ({$newQuantity} {$drug->dosage_form})",
            'notes' => $reason ? "Adjusted: {$reason}" : 'Quantity adjusted during pharmacy review',
        ]);

        return $charge->fresh();
    }

    /**
     * Void charge when prescription is marked as external.
     */
    public function voidChargeForExternal(Prescription $prescription, string $reason): Charge
    {
        $charge = $prescription->charge;

        if (! $charge) {
            throw new \Exception('No charge found for prescription');
        }

        $charge->update([
            'status' => 'cancelled',
            'amount' => 0,
            'notes' => "External prescription: {$reason}",
        ]);

        return $charge->fresh();
    }

    /**
     * Check if dispensing is allowed based on payment status and billing configuration.
     */
    public function canDispense(Prescription $prescription): bool
    {
        $requirePayment = BillingConfiguration::getValue('pharmacy.require_payment_before_dispensing', true);

        // If payment is not required by configuration, allow dispensing
        if (! $requirePayment) {
            return true;
        }

        $charge = $prescription->charge;

        // No charge means it's an external prescription or cancelled
        if (! $charge) {
            return false;
        }

        // Check if charge is paid or waived
        return in_array($charge->status, ['paid', 'waived']);
    }

    /**
     * Check if payment can be overridden (for emergency cases).
     */
    public function canOverridePayment(): bool
    {
        // Check if user has permission to override payment requirement
        return Auth::user()->can('dispensing.override-payment');
    }

    /**
     * Calculate total charges for multiple prescriptions.
     */
    public function calculateTotalCharges(array $prescriptionIds): array
    {
        $charges = Charge::whereIn('prescription_id', $prescriptionIds)
            ->where('status', '!=', 'cancelled')
            ->get();

        $total = $charges->sum('amount');
        $paid = $charges->where('status', 'paid')->sum('amount');
        $pending = $charges->where('status', 'pending')->sum('amount');

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'charges' => $charges,
        ];
    }

    /**
     * Get payment status summary for a patient's prescriptions.
     */
    public function getPaymentStatusSummary(int $patientCheckinId): array
    {
        $charges = Charge::where('patient_checkin_id', $patientCheckinId)
            ->where('service_type', 'pharmacy')
            ->whereNotNull('prescription_id')
            ->get();

        $totalAmount = $charges->sum('amount');
        $paidAmount = $charges->whereIn('status', ['paid', 'waived'])->sum('amount');
        $pendingAmount = $charges->where('status', 'pending')->sum('amount');

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => $pendingAmount,
            'all_paid' => $pendingAmount == 0,
            'has_pending' => $pendingAmount > 0,
            'charges_count' => $charges->count(),
            'paid_charges_count' => $charges->whereIn('status', ['paid', 'waived'])->count(),
            'pending_charges_count' => $charges->where('status', 'pending')->count(),
        ];
    }

    /**
     * Create a charge for a minor procedure supply when it's dispensed.
     */
    public function createSupplyCharge(\App\Models\MinorProcedureSupply $supply): Charge
    {
        $drug = $supply->drug;
        $quantity = $supply->quantity;

        // Calculate amount: unit price × quantity
        $amount = $drug->unit_price * $quantity;

        $user = Auth::user();

        // Get patient_checkin_id from the minor procedure
        $patientCheckinId = $supply->minorProcedure->patient_checkin_id;

        return Charge::create([
            'patient_checkin_id' => $patientCheckinId,
            'service_type' => 'pharmacy',
            'service_code' => $drug->drug_code,
            'description' => "Minor Procedure Supply: {$drug->name} ({$quantity} {$drug->form})",
            'amount' => $amount,
            'charge_type' => 'supply',
            'status' => 'pending',
            'charged_at' => now(),
            'created_by_type' => $user ? get_class($user) : null,
            'created_by_id' => $user?->id,
            'notes' => "Auto-generated from minor procedure supply #{$supply->id}",
            'metadata' => [
                'minor_procedure_supply_id' => $supply->id,
                'minor_procedure_id' => $supply->minor_procedure_id,
                'quantity' => $quantity,
            ],
        ]);
    }
}
