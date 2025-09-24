<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientCheckin>
 */
class PatientCheckinFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkedInAt = now()->subHours(fake()->numberBetween(1, 8)); // Today but few hours ago

        return [
            'patient_id' => Patient::factory(),
            'department_id' => Department::factory(),
            'checked_in_by' => User::factory(),
            'checked_in_at' => $checkedInAt,
            'vitals_taken_at' => fake()->optional(0.7)->dateTimeBetween($checkedInAt, 'now'),
            'consultation_started_at' => null,
            'consultation_completed_at' => null,
            'status' => fake()->randomElement(['checked_in', 'vitals_taken', 'awaiting_consultation']),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_in_at' => now()->subHours(fake()->numberBetween(1, 8)),
        ]);
    }

    public function awaitingConsultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'awaiting_consultation',
        ]);
    }
}
