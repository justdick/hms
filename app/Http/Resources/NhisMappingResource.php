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
            'gdrg_tariff_id' => $this->gdrg_tariff_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Computed fields
            'item_type_label' => $this->getItemTypeLabel(),

            // Relationships - include both NHIS and G-DRG tariffs
            'nhis_tariff' => $this->when(
                $this->relationLoaded('nhisTariff') && $this->nhisTariff,
                fn () => new NhisTariffResource($this->nhisTariff)
            ),

            'gdrg_tariff' => $this->when(
                $this->relationLoaded('gdrgTariff') && $this->gdrgTariff,
                fn () => $this->formatGdrgTariff()
            ),

            // Unified tariff data - returns whichever tariff is available (NHIS or G-DRG)
            'tariff' => $this->getUnifiedTariff(),

            // Item details (loaded via polymorphic relationship)
            'item' => $this->when(
                $this->relationLoaded('item'),
                fn () => $this->formatItem()
            ),
        ];
    }

    /**
     * Format G-DRG tariff data.
     */
    protected function formatGdrgTariff(): ?array
    {
        if (! $this->gdrgTariff) {
            return null;
        }

        return [
            'id' => $this->gdrgTariff->id,
            'code' => $this->gdrgTariff->code,
            'name' => $this->gdrgTariff->name,
            'category' => $this->gdrgTariff->mdc_category,
            'price' => $this->gdrgTariff->tariff_price,
            'formatted_price' => 'GHS '.number_format($this->gdrgTariff->tariff_price, 2),
        ];
    }

    /**
     * Get unified tariff data from either NHIS or G-DRG tariff.
     */
    protected function getUnifiedTariff(): ?array
    {
        // Prefer NHIS tariff if available
        if ($this->relationLoaded('nhisTariff') && $this->nhisTariff) {
            return [
                'id' => $this->nhisTariff->id,
                'code' => $this->nhisTariff->nhis_code,
                'name' => $this->nhisTariff->name,
                'category' => $this->nhisTariff->category,
                'price' => $this->nhisTariff->price,
                'formatted_price' => 'GHS '.number_format($this->nhisTariff->price, 2),
                'source' => 'nhis',
            ];
        }

        // Fall back to G-DRG tariff
        if ($this->relationLoaded('gdrgTariff') && $this->gdrgTariff) {
            return [
                'id' => $this->gdrgTariff->id,
                'code' => $this->gdrgTariff->code,
                'name' => $this->gdrgTariff->name,
                'category' => $this->gdrgTariff->mdc_category,
                'price' => $this->gdrgTariff->tariff_price,
                'formatted_price' => 'GHS '.number_format($this->gdrgTariff->tariff_price, 2),
                'source' => 'gdrg',
            ];
        }

        return null;
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
