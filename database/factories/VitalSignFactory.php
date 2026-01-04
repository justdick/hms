<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalSign>
 */
class VitalSignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => \App\Models\Patient::factory(),
            'patient_checkin_id' => \App\Models\PatientCheckin::factory(),
            'patient_admission_id' => null,
            'recorded_by' => \App\Models\User::factory(),
            'blood_pressure_systolic' => fake()->numberBetween(90, 140),
            'blood_pressure_diastolic' => fake()->numberBetween(60, 90),
            'temperature' => fake()->numberBetween(36, 39),
            'pulse_rate' => fake()->numberBetween(60, 100),
            'respiratory_rate' => fake()->numberBetween(12, 20),
            'weight' => fake()->numberBetween(50, 100),
            'height' => fake()->randomFloat(1, 150, 190),
            'oxygen_saturation' => fake()->numberBetween(95, 100),
            'blood_sugar' => fake()->optional()->randomFloat(1, 4.0, 8.0),
            'notes' => fake()->optional()->sentence(),
            'recorded_at' => now(),
        ];
    }
}
