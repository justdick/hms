<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalsAlert>
 */
class VitalsAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vitals_schedule_id' => \App\Models\VitalsSchedule::factory(),
            'patient_admission_id' => \App\Models\PatientAdmission::factory(),
            'due_at' => now()->addMinutes($this->faker->numberBetween(30, 240)),
            'status' => 'pending',
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'due_at' => now()->addMinutes(30),
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'due',
            'due_at' => now()->subMinutes(5),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_at' => now()->subMinutes(20),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dismissed',
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'acknowledged_at' => now(),
            'acknowledged_by' => \App\Models\User::factory(),
        ]);
    }
}
