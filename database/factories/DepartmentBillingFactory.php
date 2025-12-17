<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DepartmentBilling>
 */
class DepartmentBillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departmentNames = ['General OPD', 'Pediatrics', 'Gynecology', 'Surgery', 'Emergency', 'Dental', 'ENT', 'Ophthalmology'];
        $departmentName = fake()->randomElement($departmentNames);
        $departmentCode = strtoupper(substr(str_replace(' ', '', $departmentName), 0, 4));

        return [
            'department_id' => \App\Models\Department::factory(),
            'department_code' => $departmentCode.'-'.fake()->unique()->numberBetween(100, 999),
            'department_name' => $departmentName,
            'consultation_fee' => fake()->randomFloat(2, 20, 200),
            'equipment_fee' => fake()->randomFloat(2, 0, 50),
            'emergency_surcharge' => fake()->randomFloat(2, 0, 100),
            'payment_required_before_consultation' => fake()->boolean(70),
            'emergency_override_allowed' => fake()->boolean(80),
            'payment_grace_period_minutes' => fake()->randomElement([15, 30, 60]),
            'allow_partial_payment' => fake()->boolean(50),
            'payment_plan_available' => fake()->boolean(30),
            'is_active' => true,
        ];
    }
}
