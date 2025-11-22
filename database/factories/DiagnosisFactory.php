<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'diagnosis' => fake()->words(3, true),
            'code' => fake()->regexify('[A-Z][0-9]{2}\.[0-9]{1,2}'),
            'g_drg' => fake()->optional()->regexify('[0-9]{3}'),
            'icd_10' => fake()->regexify('[A-Z][0-9]{2}\.[0-9]{1,2}'),
        ];
    }
}
