<?php

namespace Database\Factories;

use App\Models\NhisTariff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NhisTariff>
 */
class NhisTariffFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = NhisTariff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(['medicine', 'lab', 'procedure', 'consultation', 'consumable']);
        $prefix = match ($category) {
            'medicine' => 'MED',
            'lab' => 'LAB',
            'procedure' => 'PROC',
            'consultation' => 'CONS',
            'consumable' => 'CON',
        };

        return [
            'nhis_code' => $prefix.'-'.fake()->unique()->regexify('[0-9]{6}'),
            'name' => fake()->words(3, true),
            'category' => $category,
            'price' => fake()->randomFloat(2, 5, 500),
            'unit' => fake()->optional(0.7)->randomElement(['tablet', 'capsule', 'ml', 'unit', 'test', 'session']),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the tariff is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tariff is for medicine category.
     */
    public function medicine(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'medicine',
            'nhis_code' => 'MED-'.fake()->unique()->regexify('[0-9]{6}'),
            'unit' => fake()->randomElement(['tablet', 'capsule', 'ml', 'bottle']),
        ]);
    }

    /**
     * Indicate that the tariff is for lab category.
     */
    public function lab(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'lab',
            'nhis_code' => 'LAB-'.fake()->unique()->regexify('[0-9]{6}'),
            'unit' => 'test',
        ]);
    }

    /**
     * Indicate that the tariff is for procedure category.
     */
    public function procedure(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'procedure',
            'nhis_code' => 'PROC-'.fake()->unique()->regexify('[0-9]{6}'),
            'unit' => 'session',
        ]);
    }

    /**
     * Indicate that the tariff is for consultation category.
     */
    public function consultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'consultation',
            'nhis_code' => 'CONS-'.fake()->unique()->regexify('[0-9]{6}'),
            'unit' => 'visit',
        ]);
    }

    /**
     * Indicate that the tariff is for consumable category.
     */
    public function consumable(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'consumable',
            'nhis_code' => 'CON-'.fake()->unique()->regexify('[0-9]{6}'),
            'unit' => 'unit',
        ]);
    }
}
