<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GdrgTariffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'mdc_category' => $this->mdc_category,
            'tariff_price' => (float) $this->tariff_price,
            'age_category' => $this->age_category,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed fields - formatted as "Name (Code - GHS Price)"
            'display_name' => $this->display_name,
            'formatted_price' => 'GHS '.number_format((float) $this->tariff_price, 2),

            // Relationships
            'insurance_claims_count' => $this->when(
                isset($this->insurance_claims_count),
                $this->insurance_claims_count
            ),
        ];
    }
}
