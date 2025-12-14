<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drug>
 */
class DrugFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'generic_name' => fake()->words(2, true),
            'brand_name' => fake()->company(),
            'drug_code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{4}'),
            'category' => fake()->randomElement(['analgesics', 'antibiotics', 'antivirals', 'antifungals', 'cardiovascular', 'diabetes', 'respiratory', 'vitamins']),
            'form' => fake()->randomElement(['tablet', 'capsule', 'syrup', 'injection', 'cream']),
            'strength' => fake()->randomElement(['5mg', '10mg', '25mg', '50mg', '100mg', '250mg', '500mg']),
            'description' => fake()->sentence(),
            'unit_price' => fake()->randomFloat(2, 10, 500),
            'unit_type' => fake()->randomElement(['piece', 'bottle', 'vial', 'tube', 'box']),
            'minimum_stock_level' => fake()->numberBetween(10, 50),
            'maximum_stock_level' => fake()->numberBetween(100, 500),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withSpecificPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_price' => $price,
        ]);
    }

    // ========================================
    // Drug Form States for Quantity Testing
    // ========================================

    /**
     * Piece-based drug forms
     */
    public function tablet(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'tablet',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function capsule(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'capsule',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function suppository(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'suppository',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function sachet(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'sachet',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function lozenge(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'lozenge',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function pessary(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'pessary',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function enema(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'enema',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function injection(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'injection',
            'unit_type' => 'vial',
            'bottle_size' => null,
        ]);
    }

    public function ivBag(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'iv_bag',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    public function nebulizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'nebulizer',
            'unit_type' => 'vial',
            'bottle_size' => null,
        ]);
    }

    /**
     * Volume-based drug forms
     */
    public function syrup(int $bottleSize = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'syrup',
            'unit_type' => 'bottle',
            'bottle_size' => $bottleSize,
        ]);
    }

    public function suspension(int $bottleSize = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'suspension',
            'unit_type' => 'bottle',
            'bottle_size' => $bottleSize,
        ]);
    }

    /**
     * Interval-based drug forms
     */
    public function patch(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'patch',
            'unit_type' => 'piece',
            'bottle_size' => null,
        ]);
    }

    /**
     * Fixed-unit drug forms
     */
    public function cream(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'cream',
            'unit_type' => 'tube',
            'bottle_size' => null,
        ]);
    }

    public function drops(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'drops',
            'unit_type' => 'bottle',
            'bottle_size' => null,
        ]);
    }

    public function inhaler(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'inhaler',
            'unit_type' => 'device',
            'bottle_size' => null,
        ]);
    }

    public function combinationPack(): static
    {
        return $this->state(fn (array $attributes) => [
            'form' => 'combination_pack',
            'unit_type' => 'pack',
            'bottle_size' => null,
        ]);
    }
}
