<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim_check_code' => $this->claim_check_code,
            'folder_id' => $this->folder_id,
            'patient_id' => $this->patient_id,
            'patient_insurance_id' => $this->patient_insurance_id,
            'patient_checkin_id' => $this->patient_checkin_id,
            'consultation_id' => $this->consultation_id,
            'patient_admission_id' => $this->patient_admission_id,

            // Patient details
            'patient_surname' => $this->patient_surname,
            'patient_other_names' => $this->patient_other_names,
            'patient_full_name' => $this->patient_surname.' '.$this->patient_other_names,
            'patient_dob' => $this->patient_dob?->toDateString(),
            'patient_gender' => $this->patient_gender,
            'membership_id' => $this->membership_id,

            // Visit details
            'date_of_attendance' => $this->date_of_attendance?->toDateString(),
            'date_of_discharge' => $this->date_of_discharge?->toDateString(),
            'type_of_service' => $this->type_of_service,
            'type_of_attendance' => $this->type_of_attendance,
            'specialty_attended' => $this->specialty_attended,
            'attending_prescriber' => $this->attending_prescriber,
            'is_unbundled' => $this->is_unbundled,
            'is_pharmacy_included' => $this->is_pharmacy_included,

            // Diagnosis
            'primary_diagnosis_code' => $this->primary_diagnosis_code,
            'primary_diagnosis_description' => $this->primary_diagnosis_description,
            'secondary_diagnoses' => $this->secondary_diagnoses,
            'c_drg_code' => $this->c_drg_code,
            'gdrg_tariff_id' => $this->gdrg_tariff_id,
            'gdrg_amount' => $this->gdrg_amount,
            'hin_number' => $this->hin_number,

            // Financial
            'total_claim_amount' => $this->total_claim_amount,
            'approved_amount' => $this->approved_amount,
            'patient_copay_amount' => $this->patient_copay_amount,
            'insurance_covered_amount' => $this->insurance_covered_amount,

            // Workflow
            'status' => $this->status,
            'vetted_by' => $this->vetted_by,
            'vetted_at' => $this->vetted_at?->toISOString(),
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'submission_date' => $this->submission_date?->toDateString(),
            'approval_date' => $this->approval_date?->toDateString(),
            'payment_date' => $this->payment_date?->toDateString(),
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'patient_insurance' => $this->when($this->relationLoaded('patientInsurance'), fn () => PatientInsuranceResource::make($this->patientInsurance)->resolve()),
            'items' => $this->when($this->relationLoaded('items'), fn () => InsuranceClaimItemResource::collection($this->items)->resolve()),
            'items_count' => $this->when($this->relationLoaded('items'), fn () => $this->items->count()),
            'vetted_by_user' => $this->when($this->relationLoaded('vettedBy') && $this->vettedBy, fn () => [
                'id' => $this->vettedBy->id,
                'name' => $this->vettedBy->name,
            ]),
            'submitted_by_user' => $this->when($this->relationLoaded('submittedBy') && $this->submittedBy, fn () => [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
            ]),
        ];
    }
}
