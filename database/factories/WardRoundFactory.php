<?php

namespace Database\Factories;

use App\Models\PatientAdmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WardRound>
 */
class WardRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_admission_id' => PatientAdmission::factory(),
            'doctor_id' => User::factory(),
            'day_number' => $this->faker->numberBetween(1, 30),
            'round_type' => 'daily_round',
            'round_datetime' => now(),
            'status' => 'in_progress',
        ];
    }

    /**
     * Indicate that the ward round is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
