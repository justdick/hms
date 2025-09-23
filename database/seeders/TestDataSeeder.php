<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users with roles
        $admin = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@hms.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('Admin');

        $receptionist = \App\Models\User::create([
            'name' => 'Jane Receptionist',
            'email' => 'receptionist@hms.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $receptionist->assignRole('Receptionist');

        $nurse = \App\Models\User::create([
            'name' => 'Mary Nurse',
            'email' => 'nurse@hms.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $nurse->assignRole('Nurse');

        $doctor = \App\Models\User::create([
            'name' => 'Dr. John Smith',
            'email' => 'doctor@hms.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $doctor->assignRole('Doctor');

        // Create sample patients
        $patients = [
            [
                'patient_number' => 'PAT2025000001',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'gender' => 'male',
                'date_of_birth' => '1985-03-15',
                'phone_number' => '+255123456789',
                'address' => '123 Main Street, Dar es Salaam',
                'emergency_contact_name' => 'Jane Doe',
                'emergency_contact_phone' => '+255987654321',
                'national_id' => '19850315123456',
                'status' => 'active',
            ],
            [
                'patient_number' => 'PAT2025000002',
                'first_name' => 'Mary',
                'last_name' => 'Johnson',
                'gender' => 'female',
                'date_of_birth' => '1992-07-22',
                'phone_number' => '+255111222333',
                'address' => '456 Oak Avenue, Mwanza',
                'emergency_contact_name' => 'Robert Johnson',
                'emergency_contact_phone' => '+255444555666',
                'national_id' => '19920722654321',
                'status' => 'active',
            ],
            [
                'patient_number' => 'PAT2025000003',
                'first_name' => 'Peter',
                'last_name' => 'Mwangi',
                'gender' => 'male',
                'date_of_birth' => '1978-11-08',
                'phone_number' => '+255777888999',
                'address' => '789 Cedar Road, Arusha',
                'emergency_contact_name' => 'Grace Mwangi',
                'emergency_contact_phone' => '+255333222111',
                'national_id' => '19781108987654',
                'status' => 'active',
            ],
            [
                'patient_number' => 'PAT2025000004',
                'first_name' => 'Sarah',
                'last_name' => 'Wilson',
                'gender' => 'female',
                'date_of_birth' => '2010-05-12',
                'phone_number' => '+255666777888',
                'address' => '321 Pine Street, Dodoma',
                'emergency_contact_name' => 'David Wilson',
                'emergency_contact_phone' => '+255999888777',
                'national_id' => '20100512112233',
                'status' => 'active',
            ],
            [
                'patient_number' => 'PAT2025000005',
                'first_name' => 'Ahmed',
                'last_name' => 'Hassan',
                'gender' => 'male',
                'date_of_birth' => '1965-12-03',
                'phone_number' => '+255555444333',
                'address' => '654 Elm Boulevard, Zanzibar',
                'emergency_contact_name' => 'Fatima Hassan',
                'emergency_contact_phone' => '+255222333444',
                'national_id' => '19651203445566',
                'status' => 'active',
            ],
        ];

        foreach ($patients as $patientData) {
            \App\Models\Patient::create($patientData);
        }

        $this->command->info('Test users and sample patients created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Admin: admin@hms.com / password');
        $this->command->info('Receptionist: receptionist@hms.com / password');
        $this->command->info('Nurse: nurse@hms.com / password');
        $this->command->info('Doctor: doctor@hms.com / password');
    }
}
