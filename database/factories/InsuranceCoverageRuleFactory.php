<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsuranceCoverageRule>
 */
class InsuranceCoverageRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'insurance_plan_id' => \App\Models\InsurancePlan::factory(),
            'coverage_category' => fake()->randomElement(['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing']),
            'item_code' => fake()->optional()->regexify('[A-Z0-9]{6}'),
            'item_description' => fake()->optional()->words(3, true),
            'is_covered' => fake()->boolean(85),
            'coverage_type' => fake()->randomElement(['percentage', 'fixed', 'full', 'excluded']),
            'coverage_value' => fake()->randomFloat(2, 50, 100),
            'tariff_amount' => null,
            'patient_copay_percentage' => 0,
            'patient_copay_amount' => 0,
            'is_unmapped' => false,
            'max_quantity_per_visit' => null,
            'max_amount_per_visit' => null,
            'requires_preauthorization' => fake()->boolean(20),
            'is_active' => true,
            'effective_from' => null,
            'effective_to' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that this is a flexible copay rule for an unmapped item.
     */
    public function unmapped(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unmapped' => true,
            'coverage_type' => 'fixed',
            'coverage_value' => 0,
            'patient_copay_amount' => fake()->randomFloat(2, 5, 50),
        ]);
    }
}
