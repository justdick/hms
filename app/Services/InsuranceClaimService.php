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
     * NHIS Type of Attendance codes
     */
    public const ATTENDANCE_TYPES = [
        'EAE' => 'Emergency/Acute Episode',
        'ANC' => 'Antenatal Care',
        'PNC' => 'Postnatal Care',
        'FP' => 'Family Planning',
        'CWC' => 'Child Welfare Clinic',
        'REV' => 'Review/Follow-up',
    ];

    /**
     * NHIS Type of Service codes
     */
    public const SERVICE_TYPES = [
        'OPD' => 'Outpatient',
        'IPD' => 'Inpatient',
    ];

    /**
     * NHIS Specialty codes
     */
    public const SPECIALTY_CODES = [
        'OPDC' => 'OPD Clinic (General)',
        'DENT' => 'Dental',
        'ENT' => 'ENT',
        'EYE' => 'Eye/Ophthalmology',
        'OBGY' => 'Obstetrics & Gynaecology',
        'PAED' => 'Paediatrics',
        'SURG' => 'Surgery',
        'ORTH' => 'Orthopaedics',
        'PSYC' => 'Psychiatry',
        'DERM' => 'Dermatology',
        'PHYS' => 'Physiotherapy',
        'MEDI' => 'Internal Medicine',
    ];

    /**
     * Create a new insurance claim for a patient check-in
     */
    public function createClaim(
        string $claimCheckCode,
        int $patientId,
        int $patientInsuranceId,
        int $patientCheckinId,
        ?string $typeOfService = null,
        ?Carbon $dateOfAttendance = null
    ): InsuranceClaim {
        $patient = Patient::findOrFail($patientId);
        $patientInsurance = PatientInsurance::findOrFail($patientInsuranceId);
        $checkin = PatientCheckin::with(['department', 'consultation.doctor'])->findOrFail($patientCheckinId);

        $dateOfAttendance = $dateOfAttendance ?? now();

        // Auto-detect type of service if not provided
        if ($typeOfService === null) {
            $typeOfService = $this->determineTypeOfService($checkin);
        }

        // Determine specialty from department
        $specialtyAttended = $this->mapDepartmentToSpecialty($checkin->department);

        // Get attending prescriber from consultation
        $attendingPrescriber = $checkin->consultation?->doctor?->name ?? null;

        // Map visit type to NHIS attendance type code
        $typeOfAttendance = $this->mapVisitTypeToAttendanceCode($checkin->visit_type);

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
            'type_of_attendance' => $typeOfAttendance,
            'specialty_attended' => $specialtyAttended,
            'attending_prescriber' => $attendingPrescriber,
            'status' => 'pending_vetting',
        ]);
    }

    /**
     * Map department to NHIS specialty code
     */
    protected function mapDepartmentToSpecialty($department): string
    {
        if (! $department) {
            return 'OPDC';
        }

        $name = strtolower($department->name ?? '');

        // Map common department names to NHIS specialty codes
        return match (true) {
            str_contains($name, 'dental') => 'DENT',
            str_contains($name, 'ent') => 'ENT',
            str_contains($name, 'eye') || str_contains($name, 'ophthal') => 'EYE',
            str_contains($name, 'gynae') || str_contains($name, 'gyna') => 'GYNA',
            str_contains($name, 'obstet') || str_contains($name, 'maternity') => 'OBST',
            str_contains($name, 'paed') || str_contains($name, 'child') => 'PAED',
            str_contains($name, 'surg') => 'SURG',
            str_contains($name, 'ortho') => 'ORTH',
            str_contains($name, 'psych') || str_contains($name, 'mental') => 'PSYC',
            str_contains($name, 'derm') || str_contains($name, 'skin') => 'DERM',
            str_contains($name, 'physio') => 'PHYS',
            str_contains($name, 'medicine') || str_contains($name, 'internal') => 'MEDI',
            default => 'OPDC',
        };
    }

    /**
     * Determine the type of service (inpatient/outpatient) for a check-in
     *
     * A check-in is considered inpatient if:
     * 1. It was created during an admission (created_during_admission = true)
     * 2. The consultation from this check-in led to an admission (excluding legacy migrated data)
     */
    protected function determineTypeOfService(PatientCheckin $checkin): string
    {
        // Check if created during admission
        if ($checkin->created_during_admission) {
            return 'IPD';
        }

        // Check if the consultation from this check-in led to an admission
        // Exclude legacy admissions migrated from old system (migrated_from_mittag)
        $ledToAdmission = \App\Models\PatientAdmission::where('migrated_from_mittag', false)
            ->whereHas('consultation', function ($query) use ($checkin) {
                $query->where('patient_checkin_id', $checkin->id);
            })->exists();

        return $ledToAdmission ? 'IPD' : 'OPD';
    }

    /**
     * Map visit type to NHIS attendance type code
     */
    protected function mapVisitTypeToAttendanceCode(?string $visitType): string
    {
        if (! $visitType) {
            return 'EAE';
        }

        $visitType = strtolower($visitType);

        return match (true) {
            str_contains($visitType, 'antenatal') || str_contains($visitType, 'anc') => 'ANC',
            str_contains($visitType, 'postnatal') || str_contains($visitType, 'pnc') => 'PNC',
            str_contains($visitType, 'family planning') || str_contains($visitType, 'fp') => 'FP',
            str_contains($visitType, 'child welfare') || str_contains($visitType, 'cwc') => 'CWC',
            str_contains($visitType, 'review') || str_contains($visitType, 'follow') => 'REV',
            default => 'EAE', // Default to Emergency/Acute Episode for general OPD
        };
    }

    /**
     * Add charges to an insurance claim
     */
    public function addChargesToClaim(InsuranceClaim $claim, array $chargeIds): void
    {
        $patientInsurance = $claim->patientInsurance;

        foreach ($chargeIds as $chargeId) {
            $charge = Charge::with(['prescription.drug'])->findOrFail($chargeId);

            // Get the proper item code and ID from the related entity
            $itemCode = $this->getItemCodeFromCharge($charge);
            $itemId = $this->getItemIdFromCharge($charge);

            // Determine quantity for claim
            // For certain drugs (Arthemeter, Pessary), NHIS requires qty = 1 regardless of actual dispensed qty
            $claimQuantity = $this->getClaimQuantity($charge);

            // Calculate coverage for this charge
            // Pass itemId to enable NHIS unmapped item handling with flexible copay
            $coverage = $this->insuranceService->calculateCoverage(
                $patientInsurance,
                $charge->service_type,
                $itemCode,
                (float) $charge->amount,
                $claimQuantity,
                null, // date
                $itemId
            );

            // Create claim item - unmapped items are included with insurance_pays = 0
            // This is required for NHIS auditing purposes (Requirement 4.5)
            InsuranceClaimItem::create([
                'insurance_claim_id' => $claim->id,
                'charge_id' => $chargeId,
                'item_date' => $charge->charged_at ?? now(),
                'item_type' => $this->mapServiceTypeToItemType($charge->service_type),
                'code' => $itemCode,
                'description' => $charge->description,
                'quantity' => $claimQuantity,
                'unit_tariff' => $coverage['insurance_tariff'],
                'subtotal' => $coverage['subtotal'],
                'is_covered' => $coverage['is_covered'],
                'coverage_percentage' => $coverage['coverage_percentage'],
                'insurance_pays' => $coverage['insurance_pays'],
                'patient_pays' => $coverage['patient_pays'],
                'is_approved' => null,
                'is_unmapped' => $coverage['is_unmapped'] ?? false,
                'has_flexible_copay' => $coverage['has_flexible_copay'] ?? false,
            ]);
        }

        // Recalculate claim totals
        $this->recalculateClaimTotals($claim);
    }

    /**
     * Get the quantity to use for NHIS claims.
     * Some drugs (e.g., Arthemeter, Pessary) are counted as 1 pack regardless of actual dispensed qty.
     */
    protected function getClaimQuantity(Charge $charge): int
    {
        $actualQuantity = $charge->metadata['quantity'] ?? 1;

        // Check if this is a pharmacy charge with a drug that requires qty = 1 for NHIS
        if ($charge->service_type === 'pharmacy' && $charge->prescription?->drug) {
            $drug = $charge->prescription->drug;

            if ($drug->nhis_claim_qty_as_one) {
                return 1;
            }
        }

        return $actualQuantity;
    }

    /**
     * Get the item ID from a charge by looking at related entities.
     */
    protected function getItemIdFromCharge(Charge $charge): ?int
    {
        // For pharmacy charges, get drug ID from prescription
        if ($charge->service_type === 'pharmacy' && $charge->prescription?->drug) {
            return $charge->prescription->drug->id;
        }

        // For lab charges, get service ID from metadata
        if (in_array($charge->service_type, ['lab', 'laboratory'])) {
            return $charge->metadata['lab_service_id'] ?? null;
        }

        // For procedure charges, get procedure type ID from metadata
        if ($charge->service_type === 'procedure') {
            return $charge->metadata['procedure_type_id'] ?? null;
        }

        // For consultation charges, get department billing ID
        if ($charge->service_type === 'consultation') {
            return $charge->metadata['department_billing_id'] ?? null;
        }

        return null;
    }

    /**
     * Get the proper item code from a charge by looking at related entities.
     */
    protected function getItemCodeFromCharge(Charge $charge): string
    {
        // If service_code is set, use it
        if ($charge->service_code) {
            return $charge->service_code;
        }

        // For pharmacy charges, get drug code from prescription
        if ($charge->service_type === 'pharmacy' && $charge->prescription?->drug) {
            return $charge->prescription->drug->drug_code ?? $charge->charge_type;
        }

        // For lab charges, get service code from metadata or service_code
        if (in_array($charge->service_type, ['lab', 'laboratory'])) {
            return $charge->metadata['service_code'] ?? $charge->charge_type;
        }

        // For procedure charges, get procedure code from metadata
        if ($charge->service_type === 'procedure') {
            return $charge->metadata['procedure_code'] ?? $charge->charge_type;
        }

        // Fallback to charge_type
        return $charge->charge_type ?? 'GENERAL';
    }

    /**
     * Map service type to claim item type.
     */
    protected function mapServiceTypeToItemType(string $serviceType): string
    {
        return match ($serviceType) {
            'pharmacy', 'medication' => 'drug',
            'lab', 'laboratory' => 'lab',
            'procedure', 'minor_procedure' => 'procedure',
            'consultation' => 'consultation',
            'consumable' => 'consumable',
            default => $serviceType,
        };
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
