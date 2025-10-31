<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InsuranceTariffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_plan_id' => $this->insurance_plan_id,
            'item_type' => $this->item_type,
            'item_code' => $this->item_code,
            'item_description' => $this->item_description,
            'standard_price' => $this->standard_price,
            'insurance_tariff' => $this->insurance_tariff,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'plan' => new InsurancePlanResource($this->whenLoaded('plan')),
        ];
    }
}
