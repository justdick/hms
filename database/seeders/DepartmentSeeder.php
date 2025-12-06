<?php

namespace Database\Seeders;

use App\Models\Department;
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
                'code' => 'OPDC',
                'description' => 'General Outpatient Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Adult Surgery',
                'code' => 'ASUR',
                'description' => 'Adult Surgery Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Dental',
                'code' => 'DENT',
                'description' => 'Dental Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Ear, Nose and Throat',
                'code' => 'ENTH',
                'description' => 'ENT Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Medicine',
                'code' => 'MEDI',
                'description' => 'Medicine Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Obstetrics and Gynaecology',
                'code' => 'OBGY',
                'description' => 'Obstetrics and Gynaecology Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Orthopaedics',
                'code' => 'ORTH',
                'description' => 'Orthopaedics Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Antenatal/Postnatal Care',
                'code' => 'ANCP',
                'description' => 'Antenatal and Postnatal Care',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Paediatrics',
                'code' => 'PAED',
                'description' => 'Paediatrics Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Paediatric Surgery',
                'code' => 'PSUR',
                'description' => 'Paediatric Surgery Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Reconstructive Plastic Surgery',
                'code' => 'RSUR',
                'description' => 'Reconstructive Plastic Surgery Department',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Minor Procedures',
                'code' => 'ZOOM',
                'description' => 'Circumcisions, Dressings, Catheter Change, etc.',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Ophthalmology',
                'code' => 'OPTH',
                'description' => 'Eye Clinic',
                'type' => 'opd',
                'is_active' => true,
            ],
            [
                'name' => 'Physiotherapy',
                'code' => 'PHYS',
                'description' => 'Physiotherapy Department',
                'type' => 'opd',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department
            );
        }
    }
}
