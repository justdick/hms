<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // OPD Module Access
            'opd.access' => 'Access OPD Module',

            // Patient Management
            'opd.patient.search' => 'Search Patients',
            'opd.patient.register' => 'Register New Patients',
            'opd.patient.view' => 'View Patient Details',
            'opd.patient.edit' => 'Edit Patient Information',

            // Patient Check-in
            'opd.checkin.view' => 'View Check-in Interface',
            'opd.checkin.create' => 'Check-in Patients',
            'opd.checkin.manage' => 'Manage Patient Check-ins',

            // Vitals Management
            'opd.vitals.view' => 'View Patient Vitals',
            'opd.vitals.record' => 'Record Patient Vitals',
            'opd.vitals.edit' => 'Edit Patient Vitals',

            // Consultation Management
            'opd.consultation.view' => 'View Consultations',
            'opd.consultation.create' => 'Create Consultations',
            'opd.consultation.manage' => 'Manage Consultations',

            // Department Assignment
            'opd.department.assign' => 'Assign Patients to Departments',
            'opd.department.manage' => 'Manage Departments',

            // Reports and Analytics
            'opd.reports.view' => 'View OPD Reports',
            'opd.analytics.view' => 'View OPD Analytics',
        ];

        foreach ($permissions as $name => $description) {
            \Spatie\Permission\Models\Permission::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // Create roles
        $receptionist = \Spatie\Permission\Models\Role::create([
            'name' => 'Receptionist',
            'guard_name' => 'web',
        ]);

        $nurse = \Spatie\Permission\Models\Role::create([
            'name' => 'Nurse',
            'guard_name' => 'web',
        ]);

        $doctor = \Spatie\Permission\Models\Role::create([
            'name' => 'Doctor',
            'guard_name' => 'web',
        ]);

        $admin = \Spatie\Permission\Models\Role::create([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        // Assign permissions to roles
        $receptionist->givePermissionTo([
            'opd.access',
            'opd.patient.search',
            'opd.patient.register',
            'opd.patient.view',
            'opd.patient.edit',
            'opd.checkin.view',
            'opd.checkin.create',
            'opd.department.assign',
        ]);

        $nurse->givePermissionTo([
            'opd.access',
            'opd.patient.view',
            'opd.checkin.view',
            'opd.checkin.manage',
            'opd.vitals.view',
            'opd.vitals.record',
            'opd.vitals.edit',
            'opd.consultation.view',
        ]);

        $doctor->givePermissionTo([
            'opd.access',
            'opd.patient.view',
            'opd.patient.edit',
            'opd.checkin.view',
            'opd.vitals.view',
            'opd.consultation.view',
            'opd.consultation.create',
            'opd.consultation.manage',
            'opd.reports.view',
        ]);

        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::all());
    }
}
