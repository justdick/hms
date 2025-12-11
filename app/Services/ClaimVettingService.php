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
            'checkin.department',
            'checkin.consultation.doctor',
            'checkin.consultation.diagnoses.diagnosis',
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
        // Fall back to consultation doctor if attending_prescriber is not set
        // Check both direct consultation and via checkin
        $attendingPrescriber = $claim->attending_prescriber;
        if (empty($attendingPrescriber)) {
            $doctor = $claim->consultation?->doctor ?? $claim->checkin?->consultation?->doctor;
            $attendingPrescriber = $doctor?->name;
        }

        // Fall back to department-based specialty if not set
        $specialtyAttended = $claim->specialty_attended;
        if (empty($specialtyAttended)) {
            $specialtyAttended = $this->mapDepartmentToSpecialty($claim->checkin?->department);
        }

        // Map old values to NHIS codes
        $typeOfAttendance = $this->mapToNhisAttendanceCode($claim->type_of_attendance);
        $typeOfService = $this->mapToNhisServiceCode($claim->type_of_service);

        return [
            'type_of_attendance' => $typeOfAttendance,
            'date_of_attendance' => $claim->date_of_attendance,
            'date_of_discharge' => $claim->date_of_discharge,
            'type_of_service' => $typeOfService,
            'specialty_attended' => $specialtyAttended ?? 'OPDC',
            'attending_prescriber' => $attendingPrescriber,
            'claim_check_code' => $claim->claim_check_code,
            'is_unbundled' => $claim->is_unbundled,
            'is_pharmacy_included' => $claim->is_pharmacy_included,
            // Include NHIS code options for editing
            'attendance_type_options' => InsuranceClaimService::ATTENDANCE_TYPES,
            'service_type_options' => InsuranceClaimService::SERVICE_TYPES,
            'specialty_options' => InsuranceClaimService::SPECIALTY_CODES,
        ];
    }

    /**
     * Map old attendance type values to NHIS codes.
     */
    protected function mapToNhisAttendanceCode(?string $value): string
    {
        if (empty($value)) {
            return 'EAE';
        }

        // If already a valid NHIS code, return as-is
        if (array_key_exists($value, InsuranceClaimService::ATTENDANCE_TYPES)) {
            return $value;
        }

        // Map old values to NHIS codes
        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'emergency') => 'EAE',
            str_contains($value, 'acute') => 'EAE',
            str_contains($value, 'routine') => 'EAE',
            str_contains($value, 'antenatal') || str_contains($value, 'anc') => 'ANC',
            str_contains($value, 'postnatal') || str_contains($value, 'pnc') => 'PNC',
            str_contains($value, 'review') || str_contains($value, 'follow') => 'REV',
            default => 'EAE',
        };
    }

    /**
     * Map old service type values to NHIS codes.
     */
    protected function mapToNhisServiceCode(?string $value): string
    {
        if (empty($value)) {
            return 'OPD';
        }

        // If already a valid NHIS code, return as-is
        if (array_key_exists($value, InsuranceClaimService::SERVICE_TYPES)) {
            return $value;
        }

        // Map old values to NHIS codes
        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'inpatient') || str_contains($value, 'ipd') => 'IPD',
            default => 'OPD',
        };
    }

    /**
     * Map department to NHIS specialty code.
     */
    protected function mapDepartmentToSpecialty($department): string
    {
        if (! $department) {
            return 'OPDC';
        }

        $name = strtolower($department->name ?? '');

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
                    'name' => $claimDiagnosis->diagnosis->diagnosis ?? '',
                    'icd_code' => $claimDiagnosis->diagnosis->icd_10 ?? '',
                    'is_primary' => $claimDiagnosis->is_primary,
                ];
            });
        }

        // Get consultation - either directly linked or via checkin
        $consultation = $claim->consultation ?? $claim->checkin?->consultation;

        // Fall back to consultation diagnoses (only principal diagnoses for claims)
        if ($consultation) {
            // Ensure diagnoses are loaded with their diagnosis relationship
            $consultation->loadMissing('diagnoses.diagnosis');

            // Only include principal diagnoses - provisional diagnoses are not submitted to NHIS
            return $consultation->diagnoses
                ->where('type', 'principal')
                ->map(function ($consultationDiagnosis) {
                    return [
                        'id' => null,
                        'diagnosis_id' => $consultationDiagnosis->diagnosis_id,
                        'name' => $consultationDiagnosis->diagnosis->diagnosis ?? '',
                        'icd_code' => $consultationDiagnosis->diagnosis->icd_10 ?? '',
                        'is_primary' => true,
                    ];
                })->values();
        }

        return collect();
    }

    /**
     * Get claim items grouped by type.
     * For NHIS claims, enriches items with NHIS tariff data and correct quantities.
     */
    protected function getClaimItems(InsuranceClaim $claim): array
    {
        $isNhis = $claim->isNhisClaim();

        // Load items with charge and prescription for NHIS lookup and quantity
        $claim->load(['items.charge.prescription.drug']);

        $items = $claim->items;

        // Enrich items with correct quantities and NHIS tariff data
        $items = $items->map(function ($item) use ($isNhis) {
            // Fix quantity from prescription if available (for drugs)
            if ($item->item_type === 'drug' && $item->charge?->prescription) {
                $prescription = $item->charge->prescription;
                $correctQuantity = $prescription->quantity_to_dispense ?? $prescription->quantity ?? 1;
                if ($item->quantity !== $correctQuantity) {
                    $item->quantity = $correctQuantity;
                }
            }

            // For NHIS claims, enrich with NHIS tariff data
            if ($isNhis && $item->nhis_price === null) {
                // Determine item type for NHIS lookup
                $itemType = $this->mapClaimItemTypeToNhisType($item->item_type);
                if ($itemType !== null) {
                    // Get the item ID from the charge
                    $itemId = $this->getItemIdFromClaimItem($item);
                    if ($itemId !== null) {
                        // Look up NHIS tariff (for display only, not saved yet)
                        $nhisTariff = $this->nhisTariffService->getTariffForItem($itemType, $itemId);
                        if ($nhisTariff) {
                            // Set values on the model instance (not saved to DB)
                            $item->nhis_tariff_id = $nhisTariff->id;
                            $item->nhis_code = $nhisTariff->nhis_code;
                            $item->nhis_price = $nhisTariff->price;
                        }
                    }
                }
            }

            return $item;
        });

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
        // Load items with charge and prescription (the only relationship that exists on Charge)
        $claim->load(['items.charge.prescription.drug']);

        foreach ($claim->items as $item) {
            // Skip if already has NHIS price
            if ($item->nhis_price !== null) {
                continue;
            }

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
        // Try to get from charge metadata first (most reliable for this system)
        if ($item->charge) {
            // For pharmacy/drug items, get drug_id from prescription
            if ($item->charge->prescription?->drug_id) {
                return (int) $item->charge->prescription->drug_id;
            }

            // Fallback to metadata for all item types
            $metadata = $item->charge->metadata ?? [];
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
