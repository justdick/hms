<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_checkin_id' => \App\Models\PatientCheckin::factory(),
            'doctor_id' => \App\Models\User::factory(),
            'started_at' => $this->faker->dateTimeBetween('-2 hours', 'now'),
            'completed_at' => $this->faker->optional(0.7)->dateTimeBetween('now', '+1 hour'),
            'status' => $this->faker->randomElement(['in_progress', 'completed', 'paused']),
            'chief_complaint' => $this->faker->randomElement([
                'Chest pain and shortness of breath',
                'Persistent cough for 2 weeks',
                'Abdominal pain and nausea',
                'Fever and headache',
                'Joint pain and swelling',
                'Fatigue and dizziness',
                'Skin rash on arms',
                'Back pain radiating to leg',
            ]),
            'subjective_notes' => $this->faker->paragraph(3),
            'objective_notes' => $this->faker->paragraph(2),
            'assessment_notes' => $this->faker->paragraph(2),
            'plan_notes' => $this->faker->paragraph(2),
            'follow_up_date' => $this->faker->optional(0.5)->dateTimeBetween('+1 week', '+1 month'),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
        ]);
    }
}
