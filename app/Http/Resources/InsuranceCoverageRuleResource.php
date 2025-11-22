<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceCoverageRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_plan_id' => $this->insurance_plan_id,
            'coverage_category' => $this->coverage_category,
            'item_code' => $this->item_code,
            'item_description' => $this->item_description,
            'is_covered' => $this->is_covered,
            'coverage_type' => $this->coverage_type,
            'coverage_value' => $this->coverage_value,
            'patient_copay_percentage' => $this->patient_copay_percentage,
            'max_quantity_per_visit' => $this->max_quantity_per_visit,
            'max_amount_per_visit' => $this->max_amount_per_visit,
            'requires_preauthorization' => $this->requires_preauthorization,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'plan' => new InsurancePlanResource($this->whenLoaded('plan')),
            'tariff' => $this->when($this->relationLoaded('tariff') && $this->tariff, function () {
                return [
                    'id' => $this->tariff->id,
                    'insurance_tariff' => $this->tariff->insurance_tariff,
                    'standard_price' => $this->tariff->standard_price,
                ];
            }),
        ];
    }
}
