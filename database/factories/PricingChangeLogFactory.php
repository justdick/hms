<?php

namespace Database\Factories;

use App\Models\PricingChangeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingChangeLog>
 */
class PricingChangeLogFactory extends Factory
{
    protected $model = PricingChangeLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $oldValue = fake()->randomFloat(2, 10, 500);

        return [
            'item_type' => fake()->randomElement([
                PricingChangeLog::TYPE_DRUG,
                PricingChangeLog::TYPE_LAB,
                PricingChangeLog::TYPE_CONSULTATION,
                PricingChangeLog::TYPE_PROCEDURE,
            ]),
            'item_id' => fake()->numberBetween(1, 1000),
            'item_code' => fake()->optional()->bothify('???-####'),
            'field_changed' => fake()->randomElement([
                PricingChangeLog::FIELD_CASH_PRICE,
                PricingChangeLog::FIELD_COPAY,
                PricingChangeLog::FIELD_COVERAGE,
                PricingChangeLog::FIELD_TARIFF,
            ]),
            'insurance_plan_id' => null,
            'old_value' => $oldValue,
            'new_value' => $oldValue + fake()->randomFloat(2, -50, 100),
            'changed_by' => User::factory(),
        ];
    }

    /**
     * State for cash price changes.
     */
    public function cashPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_changed' => PricingChangeLog::FIELD_CASH_PRICE,
            'insurance_plan_id' => null,
        ]);
    }

    /**
     * State for copay changes.
     */
    public function copay(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_changed' => PricingChangeLog::FIELD_COPAY,
        ]);
    }

    /**
     * State for coverage changes.
     */
    public function coverage(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_changed' => PricingChangeLog::FIELD_COVERAGE,
        ]);
    }

    /**
     * State for tariff changes.
     */
    public function tariff(): static
    {
        return $this->state(fn (array $attributes) => [
            'field_changed' => PricingChangeLog::FIELD_TARIFF,
        ]);
    }

    /**
     * State for drug item type.
     */
    public function forDrug(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => PricingChangeLog::TYPE_DRUG,
        ]);
    }

    /**
     * State for lab item type.
     */
    public function forLab(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => PricingChangeLog::TYPE_LAB,
        ]);
    }

    /**
     * State for consultation item type.
     */
    public function forConsultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => PricingChangeLog::TYPE_CONSULTATION,
        ]);
    }

    /**
     * State for procedure item type.
     */
    public function forProcedure(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => PricingChangeLog::TYPE_PROCEDURE,
        ]);
    }
}
