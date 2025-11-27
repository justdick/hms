<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NhisTariffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nhis_code' => $this->nhis_code,
            'name' => $this->name,
            'category' => $this->category,
            'price' => (float) $this->price,
            'unit' => $this->unit,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed fields
            'display_name' => "{$this->name} ({$this->nhis_code})",
            'formatted_price' => 'GHS '.number_format((float) $this->price, 2),

            // Relationships
            'item_mappings_count' => $this->when(
                isset($this->item_mappings_count),
                $this->item_mappings_count
            ),
        ];
    }
}
