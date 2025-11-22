<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MinorProcedureType>
 */
class MinorProcedureTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $procedures = [
            ['name' => 'Wound Dressing', 'code' => 'MINP001', 'description' => 'Cleaning and dressing of wounds', 'price' => 50.00],
            ['name' => 'Suturing', 'code' => 'MINP002', 'description' => 'Suturing of minor lacerations', 'price' => 150.00],
            ['name' => 'Abscess Drainage', 'code' => 'MINP003', 'description' => 'Incision and drainage of abscess', 'price' => 200.00],
            ['name' => 'Foreign Body Removal', 'code' => 'MINP004', 'description' => 'Removal of foreign bodies', 'price' => 100.00],
            ['name' => 'Nail Removal', 'code' => 'MINP005', 'description' => 'Removal of ingrown toenail', 'price' => 120.00],
        ];

        $procedure = fake()->randomElement($procedures);

        return [
            'name' => $procedure['name'],
            'code' => $procedure['code'].'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => $procedure['description'],
            'price' => $procedure['price'],
        ];
    }
}
