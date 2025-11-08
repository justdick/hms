<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MedicationAdministration>
 */
class MedicationAdministrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescription_id' => \App\Models\Prescription::factory(),
            'patient_admission_id' => \App\Models\PatientAdmission::factory(),
            'scheduled_time' => fake()->dateTimeBetween('now', '+7 days'),
            'status' => 'scheduled',
            'dosage_given' => fake()->randomElement(['5mg', '10mg', '25mg', '50mg']),
            'route' => fake()->randomElement(['oral', 'IV', 'IM', 'SC']),
            'is_adjusted' => false,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'administered_by_id' => null,
            'administered_at' => null,
        ]);
    }

    public function given(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'given',
            'administered_by_id' => \App\Models\User::factory(),
            'administered_at' => now(),
        ]);
    }

    public function held(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'held',
            'notes' => 'Held by nurse',
        ]);
    }

    public function refused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refused',
            'notes' => 'Refused by patient',
        ]);
    }

    public function adjusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_adjusted' => true,
        ]);
    }
}
