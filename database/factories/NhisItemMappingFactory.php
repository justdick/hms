<?php

namespace Database\Factories;

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NhisItemMapping>
 */
class NhisItemMappingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = NhisItemMapping::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemType = fake()->randomElement(['drug', 'lab_service', 'procedure', 'consumable']);

        // Create the appropriate item based on type
        $item = match ($itemType) {
            'drug', 'consumable' => Drug::factory()->create(),
            'lab_service' => LabService::factory()->create(),
            'procedure' => MinorProcedureType::factory()->create(),
        };

        // Get the code field for this item type
        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        // Create or get an NHIS tariff with matching category
        $tariffCategory = match ($itemType) {
            'drug' => 'medicine',
            'lab_service' => 'lab',
            'procedure' => 'procedure',
            'consumable' => 'consumable',
        };

        $nhisTariff = NhisTariff::factory()->create(['category' => $tariffCategory]);

        return [
            'item_type' => $itemType,
            'item_id' => $item->id,
            'item_code' => $item->{$codeField},
            'nhis_tariff_id' => $nhisTariff->id,
        ];
    }

    /**
     * Indicate that the mapping is for a drug.
     */
    public function forDrug(?Drug $drug = null): static
    {
        return $this->state(function (array $attributes) use ($drug) {
            $drug = $drug ?? Drug::factory()->create();

            return [
                'item_type' => 'drug',
                'item_id' => $drug->id,
                'item_code' => $drug->drug_code,
                'nhis_tariff_id' => NhisTariff::factory()->medicine()->create()->id,
            ];
        });
    }

    /**
     * Indicate that the mapping is for a lab service.
     */
    public function forLabService(?LabService $labService = null): static
    {
        return $this->state(function (array $attributes) use ($labService) {
            $labService = $labService ?? LabService::factory()->create();

            return [
                'item_type' => 'lab_service',
                'item_id' => $labService->id,
                'item_code' => $labService->code,
                'nhis_tariff_id' => NhisTariff::factory()->lab()->create()->id,
            ];
        });
    }

    /**
     * Indicate that the mapping is for a procedure.
     */
    public function forProcedure(?MinorProcedureType $procedure = null): static
    {
        return $this->state(function (array $attributes) use ($procedure) {
            $procedure = $procedure ?? MinorProcedureType::factory()->create();

            return [
                'item_type' => 'procedure',
                'item_id' => $procedure->id,
                'item_code' => $procedure->code,
                'nhis_tariff_id' => NhisTariff::factory()->procedure()->create()->id,
            ];
        });
    }

    /**
     * Indicate that the mapping is for a consumable.
     */
    public function forConsumable(?Drug $drug = null): static
    {
        return $this->state(function (array $attributes) use ($drug) {
            $drug = $drug ?? Drug::factory()->create();

            return [
                'item_type' => 'consumable',
                'item_id' => $drug->id,
                'item_code' => $drug->drug_code,
                'nhis_tariff_id' => NhisTariff::factory()->consumable()->create()->id,
            ];
        });
    }

    /**
     * Use a specific NHIS tariff for the mapping.
     */
    public function withTariff(NhisTariff $tariff): static
    {
        return $this->state(fn (array $attributes) => [
            'nhis_tariff_id' => $tariff->id,
        ]);
    }
}
