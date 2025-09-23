<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'General OPD',
                'code' => 'GEN_OPD',
                'description' => 'General Outpatient Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Eye Clinic',
                'code' => 'EYE',
                'description' => 'Ophthalmology Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'ENT Clinic',
                'code' => 'ENT',
                'description' => 'Ear, Nose & Throat Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Maternity',
                'code' => 'MAT',
                'description' => 'Maternity & Obstetrics Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Pediatrics',
                'code' => 'PED',
                'description' => 'Children\'s Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Dental Clinic',
                'code' => 'DENTAL',
                'description' => 'Dental & Oral Health Department',
                'type' => 'opd',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            \App\Models\Department::create($department);
        }
    }
}
