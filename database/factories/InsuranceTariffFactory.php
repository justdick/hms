<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InsuranceTariff>
 */
class InsuranceTariffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $standardPrice = fake()->randomFloat(2, 10, 1000);

        return [
            'insurance_plan_id' => \App\Models\InsurancePlan::factory(),
            'item_type' => fake()->randomElement(['drug', 'service', 'lab', 'procedure', 'ward']),
            'item_code' => fake()->regexify('[A-Z0-9]{8}'),
            'item_description' => fake()->words(3, true),
            'standard_price' => $standardPrice,
            'insurance_tariff' => $standardPrice * fake()->randomFloat(2, 0.5, 0.9),
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'effective_to' => fake()->optional()->dateTimeBetween('now', '+2 years'),
        ];
    }
}
