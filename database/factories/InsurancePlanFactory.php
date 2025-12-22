<?php

namespace Database\Factories;

use App\Models\InsuranceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsurancePlan>
 */
class InsurancePlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $planNames = ['Gold Plan', 'Silver Plan', 'Basic Plan', 'Premium Plan', 'Standard Plan'];
        $planName = fake()->randomElement($planNames);

        return [
            'insurance_provider_id' => InsuranceProvider::factory(),
            'plan_name' => $planName,
            'plan_code' => fake()->unique()->regexify('[A-Z]{2}[0-9]{4}'),
            'plan_type' => fake()->randomElement(['individual', 'family', 'corporate']),
            'coverage_type' => fake()->randomElement(['inpatient', 'outpatient', 'comprehensive']),
            'annual_limit' => fake()->optional()->randomFloat(2, 10000, 500000),
            'visit_limit' => fake()->optional()->numberBetween(10, 100),
            'default_copay_percentage' => fake()->randomFloat(2, 0, 30),
            'consultation_default' => fake()->optional()->randomFloat(2, 70, 100),
            'drugs_default' => fake()->optional()->randomFloat(2, 60, 90),
            'labs_default' => fake()->optional()->randomFloat(2, 70, 100),
            'procedures_default' => fake()->optional()->randomFloat(2, 50, 80),
            'requires_referral' => fake()->boolean(30),
            'require_explicit_approval_for_new_items' => fake()->boolean(20),
            'is_active' => fake()->boolean(90),
            'effective_from' => fake()->dateTimeBetween('-2 years', 'now'),
            'effective_to' => fake()->optional()->dateTimeBetween('now', '+3 years'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
