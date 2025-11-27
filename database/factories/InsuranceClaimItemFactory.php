<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsuranceClaimItem>
 */
class InsuranceClaimItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitTariff = fake()->randomFloat(2, 10, 500);
        $subtotal = $quantity * $unitTariff;
        $coveragePercentage = fake()->randomFloat(2, 50, 100);
        $insurancePays = $subtotal * ($coveragePercentage / 100);
        $patientPays = $subtotal - $insurancePays;

        return [
            'insurance_claim_id' => \App\Models\InsuranceClaim::factory(),
            'charge_id' => null,
            'item_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'item_type' => fake()->randomElement(['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing']),
            'code' => fake()->regexify('[A-Z0-9]{8}'),
            'description' => fake()->words(4, true),
            'quantity' => $quantity,
            'unit_tariff' => $unitTariff,
            'subtotal' => $subtotal,
            'is_covered' => fake()->boolean(90),
            'coverage_percentage' => $coveragePercentage,
            'insurance_pays' => $insurancePays,
            'patient_pays' => $patientPays,
            'is_approved' => fake()->boolean(80),
            'rejection_reason' => null,
            'notes' => fake()->optional()->sentence(),
            'nhis_tariff_id' => null,
            'nhis_code' => null,
            'nhis_price' => null,
        ];
    }

    /**
     * Indicate that the item has NHIS mapping.
     */
    public function withNhisMapping(\App\Models\NhisTariff $tariff): static
    {
        return $this->state(fn (array $attributes) => [
            'nhis_tariff_id' => $tariff->id,
            'nhis_code' => $tariff->nhis_code,
            'nhis_price' => $tariff->price,
        ]);
    }
}
