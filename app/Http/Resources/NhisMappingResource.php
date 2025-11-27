<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NhisMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'item_code' => $this->item_code,
            'nhis_tariff_id' => $this->nhis_tariff_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed fields
            'item_type_label' => $this->getItemTypeLabel(),

            // Relationships
            'nhis_tariff' => $this->when(
                $this->relationLoaded('nhisTariff'),
                fn () => new NhisTariffResource($this->nhisTariff)
            ),

            // Item details (loaded via polymorphic relationship)
            'item' => $this->when(
                $this->relationLoaded('item'),
                fn () => $this->formatItem()
            ),
        ];
    }

    /**
     * Get a human-readable label for the item type.
     */
    protected function getItemTypeLabel(): string
    {
        return match ($this->item_type) {
            'drug' => 'Drug',
            'lab_service' => 'Lab Service',
            'procedure' => 'Procedure',
            'consumable' => 'Consumable',
            default => ucfirst($this->item_type),
        };
    }

    /**
     * Format the item details based on type.
     */
    protected function formatItem(): ?array
    {
        if (! $this->item) {
            return null;
        }

        return [
            'id' => $this->item->id,
            'name' => $this->item->name,
            'code' => $this->item_code,
            'price' => $this->item->unit_price ?? $this->item->price ?? null,
        ];
    }
}
