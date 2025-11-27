<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsuranceClaimItem;
use App\Models\NhisItemMapping;
use App\Models\PatientAdmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClaimVettingService
{
    public function __construct(
        protected NhisTariffService $nhisTariffService
    ) {}

    /**
     * Get all data needed for the vetting modal.
     *
     * @return array{
     *     claim: InsuranceClaim,
     *     patient: array,
     *     attendance: array,
     *     diagnoses: Collection,
     *     items: array{investigations: Collection, prescriptions: Collection, procedures: Collection},
     *     totals: array{investigations: float, prescriptions: float, procedures: float, gdrg: float, grand_total: float},
     *     is_nhis: bool,
     *     gdrg_tariffs: Collection
     * }
     */
    public function getVettingData(InsuranceClaim $claim): array
    {
        // Load necessary relationships
        $claim->load([
            'patient',
            'patientInsurance.plan.provider',
            'consultation.diagnoses.diagnosis',
            'consultation.doctor',
            'admission.wardRounds',
            'claimDiagnoses.diagnosis',
            'items.nhisTariff',
            'gdrgTariff',
        ]);

        $isNhis = $claim->isNhisClaim();

        // Get patient info
        $patientInfo = $this->getPatientInfo($claim);

        // Get attendance details
        $attendanceDetails = $this->getAttendanceDetails($claim);

        // Get diagnoses (from claim or consultation)
        $diagnoses = $this->getDiagnoses($claim);

        // Get claim items grouped by type
        $items = $this->getClaimItems($claim);

        // Calculate totals
        $totals = $this->calculateClaimTotal($claim, $items);

        // Get G-DRG tariffs for selection (only for NHIS claims)
        $gdrgTariffs = $isNhis ? GdrgTariff::active()->orderBy('name')->get() : collect();

        return [
            'claim' => $claim,
            'patient' => $patientInfo,
            'attendance' => $attendanceDetails,
            'diagnoses' => $diagnoses,
            'items' => $items,
            'totals' => $totals,
            'is_nhis' => $isNhis,
            'gdrg_tariffs' => $gdrgTariffs,
        ];
    }

    /**
     * Get patient information for the vetting modal.
     */
    protected function getPatientInfo(InsuranceClaim $claim): array
    {
        $patient = $claim->patient;
        $patientInsurance = $claim->patientInsurance;

        // Get NHIS info from PatientInsurance record
        $nhisExpired = false;
        if ($patientInsurance && $patientInsurance->coverage_end_date) {
            $nhisExpired = $patientInsurance->coverage_end_date->isPast();
        }

        return [
            'id' => $patient->id,
            'name' => $patient->full_name ?? "{$claim->patient_other_names} {$claim->patient_surname}",
            'surname' => $claim->patient_surname,
            'other_names' => $claim->patient_other_names,
            'date_of_birth' => $claim->patient_dob,
            'gender' => $claim->patient_gender,
            'folder_number' => $claim->folder_id,
            'nhis_member_id' => $claim->membership_id ?? $patientInsurance?->membership_id,
            'nhis_expiry_date' => $patientInsurance?->coverage_end_date,
            'is_nhis_expired' => $nhisExpired,
        ];
    }

    /**
     * Get attendance details for the vetting modal.
     */
    protected function getAttendanceDetails(InsuranceClaim $claim): array
    {
        return [
            'type_of_attendance' => $claim->type_of_attendance,
            'date_of_attendance' => $claim->date_of_attendance,
            'date_of_discharge' => $claim->date_of_discharge,
            'type_of_service' => $claim->type_of_service,
            'specialty_attended' => $claim->specialty_attended,
            'attending_prescriber' => $claim->attending_prescriber,
            'claim_check_code' => $claim->claim_check_code,
            'is_unbundled' => $claim->is_unbundled,
            'is_pharmacy_included' => $claim->is_pharmacy_included,
        ];
    }

    /**
     * Get diagnoses for the claim.
     * Returns claim-specific diagnoses if they exist, otherwise consultation diagnoses.
     */
    protected function getDiagnoses(InsuranceClaim $claim): Collection
    {
        // If claim has its own diagnoses, use those
        if ($claim->claimDiagnoses->isNotEmpty()) {
            return $claim->claimDiagnoses->map(function ($claimDiagnosis) {
                return [
                    'id' => $claimDiagnosis->id,
                    'diagnosis_id' => $claimDiagnosis->diagnosis_id,
                    'name' => $claimDiagnosis->diagnosis->name ?? '',
                    'icd_code' => $claimDiagnosis->diagnosis->icd_code ?? '',
                    'is_primary' => $claimDiagnosis->is_primary,
                ];
            });
        }

        // Fall back to consultation diagnoses
        if ($claim->consultation) {
            return $claim->consultation->diagnoses->map(function ($consultationDiagnosis) {
                return [
                    'id' => null,
                    'diagnosis_id' => $consultationDiagnosis->diagnosis_id,
                    'name' => $consultationDiagnosis->diagnosis->name ?? '',
                    'icd_code' => $consultationDiagnosis->diagnosis->icd_code ?? '',
                    'is_primary' => $consultationDiagnosis->type === 'principal',
                ];
            });
        }

        return collect();
    }

    /**
     * Get claim items grouped by type.
     */
    protected function getClaimItems(InsuranceClaim $claim): array
    {
        $items = $claim->items;

        return [
            'investigations' => $items->where('item_type', 'lab')->values(),
            'prescriptions' => $items->where('item_type', 'drug')->values(),
            'procedures' => $items->where('item_type', 'procedure')->values(),
        ];
    }

    /**
     * Calculate the claim total.
     * For NHIS claims: G-DRG tariff + Investigations + Prescriptions + Procedures
     * Excludes unmapped items from the total.
     *
     * @param  array{investigations: Collection, prescriptions: Collection, procedures: Collection}  $items
     * @return array{investigations: float, prescriptions: float, procedures: float, gdrg: float, grand_total: float, unmapped_count: int}
     */
    public function calculateClaimTotal(InsuranceClaim $claim, ?array $items = null): array
    {
        if ($items === null) {
            $claim->load('items.nhisTariff', 'gdrgTariff');
            $items = $this->getClaimItems($claim);
        }

        $isNhis = $claim->isNhisClaim();

        // Calculate subtotals for each category
        // For NHIS: only include items that have NHIS mapping (nhis_price is set)
        $investigationsTotal = $this->calculateCategoryTotal($items['investigations'], $isNhis);
        $prescriptionsTotal = $this->calculateCategoryTotal($items['prescriptions'], $isNhis);
        $proceduresTotal = $this->calculateCategoryTotal($items['procedures'], $isNhis);

        // G-DRG amount (only for NHIS claims)
        $gdrgAmount = 0.0;
        if ($isNhis && $claim->gdrgTariff) {
            $gdrgAmount = (float) $claim->gdrgTariff->tariff_price;
        } elseif ($isNhis && $claim->gdrg_amount) {
            $gdrgAmount = (float) $claim->gdrg_amount;
        }

        // Count unmapped items
        $unmappedCount = 0;
        if ($isNhis) {
            $unmappedCount = $items['investigations']->whereNull('nhis_price')->count()
                + $items['prescriptions']->whereNull('nhis_price')->count()
                + $items['procedures']->whereNull('nhis_price')->count();
        }

        $grandTotal = $gdrgAmount + $investigationsTotal + $prescriptionsTotal + $proceduresTotal;

        return [
            'investigations' => round($investigationsTotal, 2),
            'prescriptions' => round($prescriptionsTotal, 2),
            'procedures' => round($proceduresTotal, 2),
            'gdrg' => round($gdrgAmount, 2),
            'grand_total' => round($grandTotal, 2),
            'unmapped_count' => $unmappedCount,
        ];
    }

    /**
     * Calculate total for a category of items.
     * For NHIS: uses nhis_price, excludes unmapped items.
     * For non-NHIS: uses subtotal.
     */
    protected function calculateCategoryTotal(Collection $items, bool $isNhis): float
    {
        if ($isNhis) {
            // For NHIS, only sum items that have NHIS mapping
            return $items
                ->whereNotNull('nhis_price')
                ->sum(fn ($item) => (float) $item->nhis_price * (int) $item->quantity);
        }

        // For non-NHIS, use the subtotal
        return $items->sum(fn ($item) => (float) $item->subtotal);
    }

    /**
     * Aggregate items from initial consultation and all ward rounds for an admission.
     *
     * @return array{lab_orders: Collection, prescriptions: Collection, procedures: Collection}
     */
    public function aggregateAdmissionItems(PatientAdmission $admission): array
    {
        $admission->load([
            'consultation.labOrders.labService',
            'consultation.prescriptions.drug',
            'consultation.procedures.procedureType',
            'wardRounds.labOrders.labService',
            'wardRounds.prescriptions.drug',
            'wardRounds.procedures.procedureType',
        ]);

        $labOrders = collect();
        $prescriptions = collect();
        $procedures = collect();

        // Get items from initial consultation
        if ($admission->consultation) {
            $labOrders = $labOrders->merge($admission->consultation->labOrders);
            $prescriptions = $prescriptions->merge($admission->consultation->prescriptions);
            $procedures = $procedures->merge($admission->consultation->procedures);
        }

        // Get items from all ward rounds
        foreach ($admission->wardRounds as $wardRound) {
            $labOrders = $labOrders->merge($wardRound->labOrders);
            $prescriptions = $prescriptions->merge($wardRound->prescriptions);
            $procedures = $procedures->merge($wardRound->procedures);
        }

        return [
            'lab_orders' => $labOrders,
            'prescriptions' => $prescriptions,
            'procedures' => $procedures,
        ];
    }

    /**
     * Vet (approve) a claim.
     *
     * @throws InvalidArgumentException
     */
    public function vetClaim(
        InsuranceClaim $claim,
        User $vettedBy,
        ?int $gdrgTariffId = null,
        ?array $diagnosisIds = null
    ): InsuranceClaim {
        // Validate G-DRG required for NHIS claims
        if ($claim->isNhisClaim() && $gdrgTariffId === null) {
            throw new InvalidArgumentException('G-DRG selection is required for NHIS claims.');
        }

        DB::beginTransaction();

        try {
            // Update G-DRG if provided
            if ($gdrgTariffId !== null) {
                $gdrgTariff = GdrgTariff::findOrFail($gdrgTariffId);
                $claim->gdrg_tariff_id = $gdrgTariff->id;
                $claim->gdrg_amount = $gdrgTariff->tariff_price;
            }

            // Update diagnoses if provided
            if ($diagnosisIds !== null) {
                $this->updateClaimDiagnoses($claim, $diagnosisIds);
            }

            // Store NHIS prices on claim items for NHIS claims
            if ($claim->isNhisClaim()) {
                $this->storeNhisPricesOnItems($claim);
            }

            // Recalculate total_claim_amount based on G-DRG + NHIS-priced items
            // This ensures the total reflects what will be submitted to NHIA
            if ($claim->isNhisClaim()) {
                // Reload only the items relationship to get updated NHIS prices
                $claim->load('items.nhisTariff');
                $items = $this->getClaimItems($claim);
                $totals = $this->calculateClaimTotal($claim, $items);
                $claim->total_claim_amount = $totals['grand_total'];
            }

            // Update claim status to vetted
            $claim->status = 'vetted';
            $claim->vetted_by = $vettedBy->id;
            $claim->vetted_at = now();

            $claim->save();

            DB::commit();

            return $claim->fresh([
                'gdrgTariff',
                'claimDiagnoses.diagnosis',
                'items.nhisTariff',
                'vettedBy',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update claim diagnoses.
     *
     * @param  array<int, array{diagnosis_id: int, is_primary: bool}>  $diagnosisIds
     */
    protected function updateClaimDiagnoses(InsuranceClaim $claim, array $diagnosisIds): void
    {
        // Remove existing claim diagnoses
        $claim->claimDiagnoses()->delete();

        // Add new diagnoses
        foreach ($diagnosisIds as $diagnosisData) {
            InsuranceClaimDiagnosis::create([
                'insurance_claim_id' => $claim->id,
                'diagnosis_id' => $diagnosisData['diagnosis_id'],
                'is_primary' => $diagnosisData['is_primary'] ?? false,
            ]);
        }
    }

    /**
     * Store NHIS prices on claim items at the time of vetting.
     * This preserves historical accuracy.
     */
    protected function storeNhisPricesOnItems(InsuranceClaim $claim): void
    {
        $claim->load('items');

        foreach ($claim->items as $item) {
            // Determine item type for NHIS lookup
            $itemType = $this->mapClaimItemTypeToNhisType($item->item_type);

            if ($itemType === null) {
                continue;
            }

            // Get the item ID from the charge or item code
            $itemId = $this->getItemIdFromClaimItem($item);

            if ($itemId === null) {
                continue;
            }

            // Look up NHIS tariff
            $nhisTariff = $this->nhisTariffService->getTariffForItem($itemType, $itemId);

            if ($nhisTariff) {
                $item->nhis_tariff_id = $nhisTariff->id;
                $item->nhis_code = $nhisTariff->nhis_code;
                $item->nhis_price = $nhisTariff->price;
                $item->save();
            }
        }
    }

    /**
     * Map claim item type to NHIS item type.
     */
    protected function mapClaimItemTypeToNhisType(string $claimItemType): ?string
    {
        return match ($claimItemType) {
            'drug', 'medication' => 'drug',
            'lab', 'investigation' => 'lab_service',
            'procedure' => 'procedure',
            'consumable' => 'consumable',
            default => null,
        };
    }

    /**
     * Get the item ID from a claim item.
     */
    protected function getItemIdFromClaimItem(InsuranceClaimItem $item): ?int
    {
        // Try to get from charge relationship
        if ($item->charge) {
            $metadata = $item->charge->metadata ?? [];

            // Check for specific item IDs in metadata
            if (isset($metadata['drug_id'])) {
                return (int) $metadata['drug_id'];
            }
            if (isset($metadata['lab_service_id'])) {
                return (int) $metadata['lab_service_id'];
            }
            if (isset($metadata['procedure_type_id'])) {
                return (int) $metadata['procedure_type_id'];
            }
        }

        // Try to find by item code in NHIS mapping
        $itemType = $this->mapClaimItemTypeToNhisType($item->item_type);
        if ($itemType && $item->code) {
            $mapping = NhisItemMapping::where('item_type', $itemType)
                ->where('item_code', $item->code)
                ->first();

            if ($mapping) {
                return $mapping->item_id;
            }
        }

        return null;
    }
}
