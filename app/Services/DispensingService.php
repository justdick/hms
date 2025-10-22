<?php

namespace App\Services;

use App\Models\Dispensing;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DispensingService
{
    public function __construct(
        protected PharmacyStockService $stockService,
        protected PharmacyBillingService $billingService
    ) {}

    /**
     * Review a prescription (Touchpoint 1).
     */
    public function reviewPrescription(Prescription $prescription, array $data, User $reviewer): Prescription
    {
        // Reload prescription with fresh relationships
        $prescription = $prescription->fresh(['charge', 'drug']);

        DB::transaction(function () use ($prescription, $data, $reviewer) {
            $action = $data['action']; // 'keep', 'partial', 'external', 'cancel'

            switch ($action) {
                case 'keep':
                    // Keep full quantity
                    $prescription->update([
                        'quantity_to_dispense' => $prescription->quantity,
                        'status' => 'reviewed',
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => now(),
                        'dispensing_notes' => $data['notes'] ?? null,
                    ]);
                    break;

                case 'partial':
                    // Adjust quantity
                    $newQuantity = $data['quantity_to_dispense'];
                    $prescription->update([
                        'quantity_to_dispense' => $newQuantity,
                        'status' => 'reviewed',
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => now(),
                        'dispensing_notes' => $data['notes'] ?? 'Partial quantity - stock limitation',
                    ]);

                    // Update charge
                    $this->billingService->updateChargeForReview(
                        $prescription,
                        $newQuantity,
                        $data['notes'] ?? 'Adjusted to available stock'
                    );
                    break;

                case 'external':
                    // Mark as external
                    $prescription->update([
                        'status' => 'not_dispensed',
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => now(),
                        'external_reason' => $data['reason'] ?? 'Patient to purchase externally',
                    ]);

                    // Void the charge
                    $this->billingService->voidChargeForExternal(
                        $prescription,
                        $data['reason'] ?? 'External dispensing'
                    );
                    break;

                case 'cancel':
                    // Cancel prescription
                    $prescription->update([
                        'status' => 'cancelled',
                        'reviewed_by' => $reviewer->id,
                        'reviewed_at' => now(),
                        'dispensing_notes' => $data['reason'] ?? 'Cancelled by pharmacy',
                    ]);

                    // Void the charge
                    if ($prescription->charge) {
                        $this->billingService->voidChargeForExternal(
                            $prescription,
                            $data['reason'] ?? 'Prescription cancelled'
                        );
                    }
                    break;
            }
        });

        return $prescription->fresh();
    }

    /**
     * Dispense a prescription (Touchpoint 2).
     */
    public function dispensePrescription(Prescription $prescription, array $data, User $dispenser): Dispensing
    {
        // Validate payment status
        if (! $this->validatePaymentStatus($prescription)) {
            throw new \Exception('Payment required before dispensing. Please ensure the patient has paid for this medication.');
        }

        // Eager load relationships based on prescription type
        if ($prescription->consultation_id) {
            $prescription->load('consultation.patientCheckin');
        } else {
            $prescription->load('prescribable.patientAdmission.patient');
        }

        return DB::transaction(function () use ($prescription, $data, $dispenser) {
            $quantityToDispense = $prescription->quantity_to_dispense ?? $prescription->quantity;

            // Deduct stock from batches
            $stockResult = $this->stockService->deductStock($prescription->drug, $quantityToDispense);

            if (! $stockResult['success']) {
                throw new \Exception("Insufficient stock. Still need {$stockResult['remaining_needed']} more units.");
            }

            // Get patient ID based on prescription type
            $patientId = $this->getPatientIdFromPrescription($prescription);

            // Create dispensing record
            $dispensing = Dispensing::create([
                'prescription_id' => $prescription->id,
                'patient_id' => $patientId,
                'drug_id' => $prescription->drug_id,
                'quantity' => $quantityToDispense,
                'batch_info' => json_encode($stockResult['deducted']),
                'dispensed_by' => $dispenser->id,
                'dispensed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Update prescription status
            $prescription->update([
                'status' => 'dispensed',
                'quantity_dispensed' => $quantityToDispense,
            ]);

            return $dispensing;
        });
    }

    /**
     * Partial dispense a prescription.
     */
    public function partialDispense(Prescription $prescription, int $quantity, array $data, User $dispenser): Dispensing
    {
        // Validate payment status
        if (! $this->validatePaymentStatus($prescription)) {
            throw new \Exception('Payment required before dispensing.');
        }

        // Eager load relationships based on prescription type
        if ($prescription->consultation_id) {
            $prescription->load('consultation.patientCheckin');
        } else {
            $prescription->load('prescribable.patientAdmission.patient');
        }

        return DB::transaction(function () use ($prescription, $quantity, $data, $dispenser) {
            // Deduct stock
            $stockResult = $this->stockService->deductStock($prescription->drug, $quantity);

            if (! $stockResult['success']) {
                throw new \Exception("Insufficient stock. Still need {$stockResult['remaining_needed']} more units.");
            }

            // Get patient ID based on prescription type
            $patientId = $this->getPatientIdFromPrescription($prescription);

            // Create dispensing record
            $dispensing = Dispensing::create([
                'prescription_id' => $prescription->id,
                'patient_id' => $patientId,
                'drug_id' => $prescription->drug_id,
                'quantity' => $quantity,
                'batch_info' => json_encode($stockResult['deducted']),
                'dispensed_by' => $dispenser->id,
                'dispensed_at' => now(),
                'notes' => $data['notes'] ?? 'Partial dispensing',
            ]);

            // Update prescription
            $totalDispensed = $prescription->quantity_dispensed + $quantity;
            $quantityToDispense = $prescription->quantity_to_dispense ?? $prescription->quantity;

            $prescription->update([
                'quantity_dispensed' => $totalDispensed,
                'status' => $totalDispensed >= $quantityToDispense ? 'dispensed' : 'partially_dispensed',
            ]);

            return $dispensing;
        });
    }

    /**
     * Get patient ID from prescription, handling both consultation and ward round prescriptions.
     */
    protected function getPatientIdFromPrescription(Prescription $prescription): int
    {
        // If prescription has a direct consultation relationship
        if ($prescription->consultation_id && $prescription->consultation) {
            return $prescription->consultation->patientCheckin->patient_id;
        }

        // If prescription belongs to a ward round (polymorphic relationship)
        if ($prescription->prescribable_type === 'App\Models\WardRound' && $prescription->prescribable) {
            return $prescription->prescribable->patientAdmission->patient_id;
        }

        throw new \Exception('Unable to determine patient ID for prescription');
    }

    /**
     * Validate if prescription can be dispensed based on payment status.
     */
    public function validatePaymentStatus(Prescription $prescription): bool
    {
        return $this->billingService->canDispense($prescription);
    }

    /**
     * Get prescriptions ready for review.
     */
    public function getPrescriptionsForReview(int $patientCheckinId): array
    {
        // Get prescriptions from consultations
        $consultationPrescriptions = Prescription::whereHas('consultation', function ($query) use ($patientCheckinId) {
            $query->where('patient_checkin_id', $patientCheckinId);
        })
            ->with(['drug', 'charge', 'consultation'])
            ->where('status', 'prescribed')
            ->whereNotNull('drug_id')
            ->get();

        // Get prescriptions from ward rounds for the same patient
        $wardRoundPrescriptions = Prescription::whereHasMorph('prescribable', ['App\Models\WardRound'], function ($query) use ($patientCheckinId) {
            $query->whereHas('patientAdmission.consultation', function ($q) use ($patientCheckinId) {
                $q->where('patient_checkin_id', $patientCheckinId);
            });
        })
            ->with(['drug', 'charge', 'prescribable.patientAdmission.consultation'])
            ->where('status', 'prescribed')
            ->whereNotNull('drug_id')
            ->get();

        // Merge both collections
        $prescriptions = $consultationPrescriptions->merge($wardRoundPrescriptions);

        return $prescriptions->map(function ($prescription) {
            $stockStatus = $this->stockService->checkAvailability($prescription->drug, $prescription->quantity);

            return [
                'prescription' => $prescription,
                'stock_status' => $stockStatus,
                'can_dispense_full' => $stockStatus['available'],
                'max_dispensable' => $stockStatus['in_stock'],
            ];
        })->toArray();
    }

    /**
     * Get prescriptions ready for dispensing.
     */
    public function getPrescriptionsForDispensing(int $patientCheckinId): array
    {
        // Get prescriptions from consultations
        $consultationPrescriptions = Prescription::whereHas('consultation', function ($query) use ($patientCheckinId) {
            $query->where('patient_checkin_id', $patientCheckinId);
        })
            ->with(['drug', 'charge', 'reviewedBy', 'dispensing'])
            ->where('status', 'reviewed')
            ->whereNotNull('drug_id')
            ->get();

        // Get prescriptions from ward rounds for the same patient
        $wardRoundPrescriptions = Prescription::whereHasMorph('prescribable', ['App\Models\WardRound'], function ($query) use ($patientCheckinId) {
            $query->whereHas('patientAdmission.consultation', function ($q) use ($patientCheckinId) {
                $q->where('patient_checkin_id', $patientCheckinId);
            });
        })
            ->with(['drug', 'charge', 'reviewedBy', 'dispensing', 'prescribable.patientAdmission.consultation'])
            ->where('status', 'reviewed')
            ->whereNotNull('drug_id')
            ->get();

        // Merge both collections
        $prescriptions = $consultationPrescriptions->merge($wardRoundPrescriptions);

        return $prescriptions->map(function ($prescription) {
            return [
                'prescription' => $prescription,
                'payment_status' => $prescription->charge?->status,
                'can_dispense' => $this->billingService->canDispense($prescription),
                'available_batches' => $this->stockService->getAvailableBatches(
                    $prescription->drug,
                    $prescription->quantity_to_dispense ?? $prescription->quantity
                ),
            ];
        })->toArray();
    }
}
