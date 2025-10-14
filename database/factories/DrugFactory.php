<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drug>
 */
class DrugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'generic_name' => fake()->words(2, true),
            'brand_name' => fake()->company(),
            'drug_code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{4}'),
            'category' => fake()->randomElement(['analgesics', 'antibiotics', 'antivirals', 'antifungals', 'cardiovascular', 'diabetes', 'respiratory', 'vitamins']),
            'form' => fake()->randomElement(['tablet', 'capsule', 'syrup', 'injection', 'cream']),
            'strength' => fake()->randomElement(['5mg', '10mg', '25mg', '50mg', '100mg', '250mg', '500mg']),
            'description' => fake()->sentence(),
            'unit_price' => fake()->randomFloat(2, 10, 500),
            'unit_type' => fake()->randomElement(['piece', 'bottle', 'vial', 'tube', 'box']),
            'minimum_stock_level' => fake()->numberBetween(10, 50),
            'maximum_stock_level' => fake()->numberBetween(100, 500),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSpecificPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => $price,
        ]);
    }
}
