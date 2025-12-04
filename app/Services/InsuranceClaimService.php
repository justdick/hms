<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InsuranceClaimService
{
    public function __construct(
        protected InsuranceService $insuranceService
    ) {}

    /**
     * Create a new insurance claim for a patient check-in
     */
    public function createClaim(
        string $claimCheckCode,
        int $patientId,
        int $patientInsuranceId,
        int $patientCheckinId,
        string $typeOfService = 'outpatient',
        ?Carbon $dateOfAttendance = null
    ): InsuranceClaim {
        $patient = Patient::findOrFail($patientId);
        $patientInsurance = PatientInsurance::findOrFail($patientInsuranceId);
        $checkin = PatientCheckin::findOrFail($patientCheckinId);

        $dateOfAttendance = $dateOfAttendance ?? now();

        // Create the claim with denormalized patient data
        return InsuranceClaim::create([
            'claim_check_code' => $claimCheckCode,
            'folder_id' => $patientInsurance->folder_id_prefix.'-'.$patient->id,
            'patient_id' => $patientId,
            'patient_insurance_id' => $patientInsuranceId,
            'patient_checkin_id' => $patientCheckinId,
            'patient_surname' => $patient->surname,
            'patient_other_names' => $patient->other_names,
            'patient_dob' => $patient->date_of_birth,
            'patient_gender' => $patient->gender,
            'membership_id' => $patientInsurance->membership_id,
            'date_of_attendance' => $dateOfAttendance,
            'type_of_service' => $typeOfService,
            'type_of_attendance' => $checkin->visit_type ?? 'routine',
            'status' => 'pending_vetting',
        ]);
    }

    /**
     * Add charges to an insurance claim
     */
    public function addChargesToClaim(InsuranceClaim $claim, array $chargeIds): void
    {
        $patientInsurance = $claim->patientInsurance;

        foreach ($chargeIds as $chargeId) {
            $charge = Charge::findOrFail($chargeId);

            // Calculate coverage for this charge
            $coverage = $this->insuranceService->calculateCoverage(
                $patientInsurance,
                $charge->service_type,
                $charge->service_code ?? $charge->charge_type,
                (float) $charge->amount,
                $charge->metadata['quantity'] ?? 1
            );

            // Create claim item
            InsuranceClaimItem::create([
                'insurance_claim_id' => $claim->id,
                'charge_id' => $chargeId,
                'item_date' => $charge->charged_at ?? now(),
                'item_type' => $charge->service_type,
                'code' => $charge->service_code ?? $charge->charge_type,
                'description' => $charge->description,
                'quantity' => $charge->metadata['quantity'] ?? 1,
                'unit_tariff' => $coverage['insurance_tariff'],
                'subtotal' => $coverage['subtotal'],
                'is_covered' => $coverage['is_covered'],
                'coverage_percentage' => $coverage['coverage_percentage'],
                'insurance_pays' => $coverage['insurance_pays'],
                'patient_pays' => $coverage['patient_pays'],
                'is_approved' => null,
            ]);
        }

        // Recalculate claim totals
        $this->recalculateClaimTotals($claim);
    }

    /**
     * Recalculate claim totals from items
     */
    public function recalculateClaimTotals(InsuranceClaim $claim): void
    {
        $items = $claim->items;

        $totalClaimAmount = $items->sum('subtotal');
        $insuranceCoveredAmount = $items->sum('insurance_pays');
        $patientCopayAmount = $items->sum('patient_pays');

        // Approved amount is only calculated after vetting
        $approvedAmount = $claim->status === 'vetted' || $claim->status === 'submitted'
            ? $items->where('is_approved', true)->sum('insurance_pays')
            : 0.00;

        $claim->update([
            'total_claim_amount' => $totalClaimAmount,
            'insurance_covered_amount' => $insuranceCoveredAmount,
            'patient_copay_amount' => $patientCopayAmount,
            'approved_amount' => $approvedAmount,
        ]);
    }

    /**
     * Vet a claim (approve/reject items)
     */
    public function vetClaim(
        InsuranceClaim $claim,
        int $vettedById,
        array $itemApprovals,
        ?string $primaryDiagnosisCode = null,
        ?string $primaryDiagnosisDescription = null,
        ?array $secondaryDiagnoses = null,
        ?string $notes = null
    ): void {
        if ($claim->status !== 'pending_vetting') {
            throw new \Exception('Only pending_vetting claims can be vetted');
        }

        DB::transaction(function () use ($claim, $vettedById, $itemApprovals, $primaryDiagnosisCode, $primaryDiagnosisDescription, $secondaryDiagnoses, $notes) {
            // Update each item's approval status
            foreach ($itemApprovals as $itemId => $approval) {
                $item = InsuranceClaimItem::findOrFail($itemId);

                if ($item->insurance_claim_id !== $claim->id) {
                    throw new \Exception("Item {$itemId} does not belong to this claim");
                }

                $item->update([
                    'is_approved' => $approval['is_approved'],
                    'rejection_reason' => $approval['rejection_reason'] ?? null,
                ]);
            }

            // Update claim
            $claim->update([
                'status' => 'vetted',
                'vetted_by' => $vettedById,
                'vetted_at' => now(),
                'primary_diagnosis_code' => $primaryDiagnosisCode,
                'primary_diagnosis_description' => $primaryDiagnosisDescription,
                'secondary_diagnoses' => $secondaryDiagnoses,
                'notes' => $notes,
            ]);

            // Recalculate approved amount
            $this->recalculateClaimTotals($claim);
        });
    }

    /**
     * Submit claim to insurance company
     */
    public function submitToInsurance(
        InsuranceClaim $claim,
        int $submittedById,
        ?Carbon $submissionDate = null
    ): void {
        if ($claim->status !== 'vetted') {
            throw new \Exception('Only vetted claims can be submitted to insurance');
        }

        $claim->update([
            'status' => 'submitted',
            'submitted_by' => $submittedById,
            'submitted_at' => now(),
            'submission_date' => $submissionDate ?? now(),
        ]);
    }

    /**
     * Mark claim as approved by insurance
     */
    public function approveClaim(
        InsuranceClaim $claim,
        float $approvedAmount,
        ?Carbon $approvalDate = null
    ): void {
        if ($claim->status !== 'submitted') {
            throw new \Exception('Only submitted claims can be approved');
        }

        $claim->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount,
            'approval_date' => $approvalDate ?? now(),
        ]);
    }

    /**
     * Reject a claim
     */
    public function rejectClaim(InsuranceClaim $claim, string $rejectionReason): void
    {
        if (! in_array($claim->status, ['submitted', 'pending_vetting', 'vetted'])) {
            throw new \Exception('Cannot reject a claim in current status');
        }

        $claim->update([
            'status' => 'rejected',
            'rejection_reason' => $rejectionReason,
        ]);
    }

    /**
     * Mark claim as paid
     */
    public function markAsPaid(InsuranceClaim $claim, ?Carbon $paymentDate = null): void
    {
        if ($claim->status !== 'approved') {
            throw new \Exception('Only approved claims can be marked as paid');
        }

        $claim->update([
            'status' => 'paid',
            'payment_date' => $paymentDate ?? now(),
        ]);
    }

    /**
     * Mark claim as partially paid
     */
    public function markAsPartiallyPaid(
        InsuranceClaim $claim,
        float $paidAmount,
        ?Carbon $paymentDate = null
    ): void {
        if (! in_array($claim->status, ['approved', 'partial'])) {
            throw new \Exception('Cannot mark claim as partial in current status');
        }

        $claim->update([
            'status' => 'partial',
            'approved_amount' => $paidAmount,
            'payment_date' => $paymentDate ?? now(),
        ]);
    }

    /**
     * Get claim summary for display
     */
    public function getClaimSummary(InsuranceClaim $claim): array
    {
        $items = $claim->items;

        $approvedItems = $items->where('is_approved', true);
        $rejectedItems = $items->where('is_approved', false);
        $pendingItems = $items->where('is_approved', null);

        return [
            'claim_check_code' => $claim->claim_check_code,
            'patient_name' => $claim->patient_surname.' '.$claim->patient_other_names,
            'membership_id' => $claim->membership_id,
            'status' => $claim->status,
            'total_items' => $items->count(),
            'approved_items' => $approvedItems->count(),
            'rejected_items' => $rejectedItems->count(),
            'pending_items' => $pendingItems->count(),
            'total_claim_amount' => $claim->total_claim_amount,
            'approved_amount' => $claim->approved_amount,
            'patient_copay_amount' => $claim->patient_copay_amount,
            'insurance_covered_amount' => $claim->insurance_covered_amount,
            'items_by_type' => $items->groupBy('item_type')->map(function ($typeItems) {
                return [
                    'count' => $typeItems->count(),
                    'subtotal' => $typeItems->sum('subtotal'),
                    'insurance_pays' => $typeItems->sum('insurance_pays'),
                    'patient_pays' => $typeItems->sum('patient_pays'),
                ];
            }),
        ];
    }

    /**
     * Auto-link charges to claim during a visit
     */
    public function autoLinkCharges(InsuranceClaim $claim): void
    {
        // Get all charges for this check-in that aren't already in a claim
        $charges = Charge::where('patient_checkin_id', $claim->patient_checkin_id)
            ->whereDoesntHave('claimItems')
            ->get();

        if ($charges->isEmpty()) {
            return;
        }

        $this->addChargesToClaim($claim, $charges->pluck('id')->toArray());
    }

    /**
     * Generate claim export data (for submission to insurance)
     */
    public function generateClaimExportData(InsuranceClaim $claim): array
    {
        $items = $claim->items()->where('is_approved', true)->get();

        return [
            'claim' => [
                'claim_check_code' => $claim->claim_check_code,
                'folder_id' => $claim->folder_id,
                'membership_id' => $claim->membership_id,
                'patient' => [
                    'surname' => $claim->patient_surname,
                    'other_names' => $claim->patient_other_names,
                    'dob' => $claim->patient_dob->format('Y-m-d'),
                    'gender' => $claim->patient_gender,
                ],
                'visit' => [
                    'date_of_attendance' => $claim->date_of_attendance->format('Y-m-d'),
                    'date_of_discharge' => $claim->date_of_discharge?->format('Y-m-d'),
                    'type_of_service' => $claim->type_of_service,
                    'type_of_attendance' => $claim->type_of_attendance,
                    'specialty_attended' => $claim->specialty_attended,
                    'attending_prescriber' => $claim->attending_prescriber,
                ],
                'diagnosis' => [
                    'primary_code' => $claim->primary_diagnosis_code,
                    'primary_description' => $claim->primary_diagnosis_description,
                    'secondary_diagnoses' => $claim->secondary_diagnoses,
                    'c_drg_code' => $claim->c_drg_code,
                ],
                'financial' => [
                    'total_claim_amount' => $claim->total_claim_amount,
                    'approved_amount' => $claim->approved_amount,
                    'patient_copay_amount' => $claim->patient_copay_amount,
                ],
            ],
            'items' => $items->map(function ($item) {
                return [
                    'date' => $item->item_date->format('Y-m-d'),
                    'type' => $item->item_type,
                    'code' => $item->code,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_tariff' => $item->unit_tariff,
                    'subtotal' => $item->subtotal,
                    'insurance_pays' => $item->insurance_pays,
                    'patient_pays' => $item->patient_pays,
                ];
            })->toArray(),
        ];
    }
}
