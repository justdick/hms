<?php

namespace Database\Factories;

use App\Models\InsurancePlan;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientInsurance>
 */
class PatientInsuranceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-2 years', '-6 months');
        $endDate = fake()->dateTimeBetween('now', '+2 years');

        return [
            'patient_id' => Patient::factory(),
            'insurance_plan_id' => InsurancePlan::factory(),
            'membership_id' => fake()->unique()->numerify('########'),
            'policy_number' => fake()->optional()->regexify('[A-Z]{3}[0-9]{7}'),
            'folder_id_prefix' => fake()->optional()->regexify('[A-Z]{2}'),
            'is_dependent' => fake()->boolean(40),
            'principal_member_name' => fake()->optional(0.4)->name(),
            'relationship_to_principal' => fake()->randomElement(['self', 'spouse', 'child', 'parent', 'other']),
            'coverage_start_date' => $startDate,
            'coverage_end_date' => $endDate,
            'status' => fake()->randomElement(['active', 'expired', 'suspended', 'cancelled']),
            'card_number' => fake()->optional()->numerify('##########'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
