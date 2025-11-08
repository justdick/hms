<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VitalsSchedule>
 */
class VitalsScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $intervalOptions = [60, 120, 240, 360, 480, 720];
        $intervalMinutes = fake()->randomElement($intervalOptions);
        $lastRecordedAt = fake()->dateTimeBetween('-2 hours', 'now');

        return [
            'patient_admission_id' => \App\Models\PatientAdmission::factory(),
            'interval_minutes' => $intervalMinutes,
            'next_due_at' => now()->addMinutes($intervalMinutes),
            'last_recorded_at' => $lastRecordedAt,
            'is_active' => true,
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function due(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_at' => now()->subMinutes(5),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_at' => now()->subMinutes(20),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_due_at' => now()->addMinutes(30),
        ]);
    }
}
