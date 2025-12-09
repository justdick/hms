<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceCoverageRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get NHIS tariff if this is a drug/lab item
        $nhisTariff = $this->getNhisTariffPrice();

        return [
            'id' => $this->id,
            'insurance_plan_id' => $this->insurance_plan_id,
            'coverage_category' => $this->coverage_category,
            'item_code' => $this->item_code,
            'item_description' => $this->item_description,
            'is_covered' => $this->is_covered,
            'coverage_type' => $this->coverage_type,
            'coverage_value' => $this->coverage_value,
            'tariff_amount' => $this->tariff_amount,
            'nhis_tariff_price' => $nhisTariff,
            'patient_copay_percentage' => $this->patient_copay_percentage,
            'patient_copay_amount' => $this->patient_copay_amount,
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

    /**
     * Get NHIS tariff price for this item if mapped.
     */
    private function getNhisTariffPrice(): ?float
    {
        if (! $this->item_code) {
            return null;
        }

        $itemType = match ($this->coverage_category) {
            'drug' => 'drug',
            'lab' => 'lab_service',
            default => null,
        };

        if (! $itemType) {
            return null;
        }

        // Find the item and its NHIS mapping
        $mapping = \App\Models\NhisItemMapping::where('item_type', $itemType)
            ->where('item_code', $this->item_code)
            ->with('nhisTariff')
            ->first();

        if ($mapping && $mapping->nhisTariff && $mapping->nhisTariff->is_active) {
            return (float) $mapping->nhisTariff->price;
        }

        return null;
    }
}
