<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceClaimItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_claim_id' => $this->insurance_claim_id,
            'charge_id' => $this->charge_id,
            'item_date' => $this->item_date?->toDateString(),
            'item_type' => $this->item_type,
            'code' => $this->code,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_tariff' => $this->unit_tariff,
            'subtotal' => $this->subtotal,
            'is_covered' => $this->is_covered,
            'coverage_percentage' => $this->coverage_percentage,
            'insurance_pays' => $this->insurance_pays,
            'patient_pays' => $this->patient_pays,
            'is_approved' => $this->is_approved,
            'rejection_reason' => $this->rejection_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
