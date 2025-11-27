<?php

namespace Database\Factories;

use App\Models\GdrgTariff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GdrgTariff>
 */
class GdrgTariffFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = GdrgTariff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mdcCategories = [
            'Out Patient',
            'In Patient',
            'Surgical',
            'Medical',
            'Obstetric',
            'Paediatric',
            'Emergency',
            'Dental',
            'Ophthalmology',
            'ENT',
        ];

        return [
            'code' => 'GDRG-'.fake()->unique()->regexify('[A-Z]{2}[0-9]{3}'),
            'name' => fake()->randomElement([
                'General Consultation',
                'Specialist Consultation',
                'Emergency Visit',
                'Follow-up Visit',
                'Surgical Consultation',
                'Antenatal Visit',
                'Postnatal Visit',
                'Child Welfare Clinic',
                'Dental Consultation',
                'Eye Examination',
            ]).' '.fake()->unique()->numberBetween(1, 999),
            'mdc_category' => fake()->randomElement($mdcCategories),
            'tariff_price' => fake()->randomFloat(2, 10, 500),
            'age_category' => fake()->randomElement(['adult', 'child', 'all']),
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
     * Indicate that the tariff is for adults only.
     */
    public function adult(): static
    {
        return $this->state(fn (array $attributes) => [
            'age_category' => 'adult',
        ]);
    }

    /**
     * Indicate that the tariff is for children only.
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'age_category' => 'child',
        ]);
    }

    /**
     * Indicate that the tariff is for all ages.
     */
    public function allAges(): static
    {
        return $this->state(fn (array $attributes) => [
            'age_category' => 'all',
        ]);
    }

    /**
     * Indicate that the tariff is for Out Patient MDC category.
     */
    public function outPatient(): static
    {
        return $this->state(fn (array $attributes) => [
            'mdc_category' => 'Out Patient',
        ]);
    }

    /**
     * Indicate that the tariff is for In Patient MDC category.
     */
    public function inPatient(): static
    {
        return $this->state(fn (array $attributes) => [
            'mdc_category' => 'In Patient',
        ]);
    }

    /**
     * Indicate that the tariff is for Surgical MDC category.
     */
    public function surgical(): static
    {
        return $this->state(fn (array $attributes) => [
            'mdc_category' => 'Surgical',
        ]);
    }

    /**
     * Indicate that the tariff is for Emergency MDC category.
     */
    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'mdc_category' => 'Emergency',
        ]);
    }

    /**
     * Set a specific code for the tariff.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Set a specific price for the tariff.
     */
    public function withPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'tariff_price' => $price,
        ]);
    }
}
