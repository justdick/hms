<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prescription>
 */
class PrescriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consultation_id' => \App\Models\Consultation::factory(),
            'drug_id' => \App\Models\Drug::factory(),
            'medication_name' => fake()->words(2, true),
            'dose_quantity' => fake()->randomElement(['5mg', '10mg', '25mg', '50mg', '100mg']),
            'frequency' => fake()->randomElement(['Once daily', 'Twice daily', 'Three times daily', 'Every 6 hours']),
            'duration' => fake()->randomElement(['3 days', '5 days', '7 days', '14 days']),
            'quantity' => fake()->numberBetween(10, 100),
            'dosage_form' => fake()->randomElement(['tablet', 'capsule', 'syrup', 'injection']),
            'instructions' => fake()->sentence(),
            'status' => 'prescribed',
            'is_unpriced' => false,
        ];
    }

    public function prescribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'prescribed',
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reviewed',
            'reviewed_by' => \App\Models\User::factory(),
            'reviewed_at' => now(),
            'quantity_to_dispense' => $attributes['quantity'],
        ]);
    }

    public function dispensed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dispensed',
            'reviewed_by' => \App\Models\User::factory(),
            'reviewed_at' => now()->subHour(),
            'quantity_to_dispense' => $attributes['quantity'],
            'quantity_dispensed' => $attributes['quantity'],
        ]);
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'not_dispensed',
            'external_reason' => 'Patient to purchase externally',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the prescription is for an unpriced drug.
     */
    public function unpriced(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_unpriced' => true,
        ]);
    }
}
