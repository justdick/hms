<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsurancePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_provider_id' => $this->insurance_provider_id,
            'plan_name' => $this->plan_name,
            'plan_code' => $this->plan_code,
            'plan_type' => $this->plan_type,
            'coverage_type' => $this->coverage_type,
            'annual_limit' => $this->annual_limit,
            'visit_limit' => $this->visit_limit,
            'default_copay_percentage' => $this->default_copay_percentage,
            'requires_referral' => $this->requires_referral,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'provider' => new InsuranceProviderResource($this->whenLoaded('provider')),
            'coverage_rules' => InsuranceCoverageRuleResource::collection($this->whenLoaded('coverageRules')),
            'tariffs' => InsuranceTariffResource::collection($this->whenLoaded('tariffs')),
            'coverage_rules_count' => $this->when(isset($this->coverage_rules_count), $this->coverage_rules_count) ?: $this->when($this->relationLoaded('coverageRules'), fn () => $this->coverageRules->count()),
            'tariffs_count' => $this->when(isset($this->tariffs_count), $this->tariffs_count) ?: $this->when($this->relationLoaded('tariffs'), fn () => $this->tariffs->count()),
        ];
    }
}
