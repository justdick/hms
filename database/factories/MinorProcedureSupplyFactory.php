<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MinorProcedureSupply>
 */
class MinorProcedureSupplyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dispensed = $this->faker->boolean(70); // 70% chance of being dispensed

        return [
            'minor_procedure_id' => \App\Models\MinorProcedure::factory(),
            'drug_id' => \App\Models\Drug::factory(),
            'quantity' => $this->faker->randomFloat(2, 0.5, 10),
            'dispensed' => $dispensed,
            'dispensed_at' => $dispensed ? $this->faker->dateTimeBetween('-2 hours', 'now') : null,
            'dispensed_by' => $dispensed ? \App\Models\User::factory() : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispensed' => false,
            'dispensed_at' => null,
            'dispensed_by' => null,
        ]);
    }

    public function dispensed(): static
    {
        return $this->state(fn (array $attributes) => [
            'dispensed' => true,
            'dispensed_at' => $this->faker->dateTimeBetween('-2 hours', 'now'),
            'dispensed_by' => \App\Models\User::factory(),
        ]);
    }
}
