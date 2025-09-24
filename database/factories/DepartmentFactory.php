<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = [
            'General OPD' => 'GEN',
            'Eye Clinic' => 'EYE',
            'ENT Department' => 'ENT',
            'Maternity Ward' => 'MAT',
            'Pediatrics' => 'PED',
            'Dental Clinic' => 'DEN',
            'Orthopedics' => 'ORT',
            'Cardiology' => 'CAR',
        ];

        $name = fake()->randomElement(array_keys($departments));
        $code = $departments[$name];

        return [
            'name' => $name,
            'code' => $code.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['opd', 'ipd', 'diagnostic', 'support']),
            'is_active' => true,
        ];
    }
}
