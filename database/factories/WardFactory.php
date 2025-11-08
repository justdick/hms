<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ward>
 */
class WardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Ward',
            'code' => strtoupper(fake()->lexify('???')),
            'description' => fake()->sentence(),
            'total_beds' => fake()->numberBetween(10, 50),
            'available_beds' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
