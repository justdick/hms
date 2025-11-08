<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientAdmission>
 */
class PatientAdmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_number' => 'ADM-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(1000, 9999),
            'patient_id' => \App\Models\Patient::factory(),
            'consultation_id' => \App\Models\Consultation::factory(),
            'ward_id' => \App\Models\Ward::factory(),
            'status' => 'admitted',
            'admission_reason' => fake()->sentence(),
            'admission_notes' => fake()->paragraph(),
            'expected_discharge_date' => fake()->dateTimeBetween('now', '+7 days'),
            'admitted_at' => now(),
        ];
    }

    public function admitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'admitted',
            'discharged_at' => null,
            'discharge_notes' => null,
            'discharged_by_id' => null,
        ]);
    }

    public function discharged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'discharged',
            'discharged_at' => now(),
            'discharge_notes' => fake()->paragraph(),
            'discharged_by_id' => \App\Models\User::factory(),
        ]);
    }
}
