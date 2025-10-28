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
            'patient_copay_percentage' => fake()->randomFloat(2, 0, 50),
            'max_quantity_per_visit' => fake()->optional()->numberBetween(1, 10),
            'max_amount_per_visit' => fake()->optional()->randomFloat(2, 100, 5000),
            'requires_preauthorization' => fake()->boolean(20),
            'is_active' => fake()->boolean(90),
            'effective_from' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
