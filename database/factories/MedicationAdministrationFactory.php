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
            'administered_by_id' => \App\Models\User::factory(),
            'administered_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'status' => 'given',
            'dosage_given' => fake()->randomElement(['5mg', '10mg', '25mg', '50mg', '100mg', '1 tablet', '2 tablets']),
            'route' => fake()->randomElement(['oral', 'IV', 'IM', 'SC', 'topical']),
            'notes' => null,
        ];
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
            'notes' => 'Held - patient NPO for procedure',
        ]);
    }

    public function refused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refused',
            'notes' => 'Patient refused medication',
        ]);
    }

    public function omitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'omitted',
            'notes' => 'Medication not available',
        ]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'administered_at' => now(),
        ]);
    }
}
