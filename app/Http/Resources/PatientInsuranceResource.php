<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientInsuranceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'insurance_plan_id' => $this->insurance_plan_id,
            'membership_id' => $this->membership_id,
            'policy_number' => $this->policy_number,
            'folder_id_prefix' => $this->folder_id_prefix,
            'is_dependent' => $this->is_dependent,
            'principal_member_name' => $this->principal_member_name,
            'relationship_to_principal' => $this->relationship_to_principal,
            'coverage_start_date' => $this->coverage_start_date?->toDateString(),
            'coverage_end_date' => $this->coverage_end_date?->toDateString(),
            'status' => $this->status,
            'card_number' => $this->card_number,
            'notes' => $this->notes,
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'plan' => new InsurancePlanResource($this->whenLoaded('plan')),
        ];
    }
}
