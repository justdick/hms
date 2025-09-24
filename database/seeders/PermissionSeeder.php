<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Patient Management
            'patients.view-all' => 'View all patients system-wide',
            'patients.view-dept' => 'View patients in assigned departments',
            'patients.create' => 'Register new patients',
            'patients.update' => 'Edit patient information',
            'patients.delete' => 'Delete patients',

            // Patient Check-in Management
            'checkins.view-all' => 'View all patient check-ins',
            'checkins.view-dept' => 'View check-ins in assigned departments',
            'checkins.create' => 'Check-in patients',
            'checkins.update' => 'Update check-in status',
            'checkins.delete' => 'Cancel check-ins',

            // Vital Signs Management
            'vitals.view-all' => 'View all patient vitals',
            'vitals.view-dept' => 'View vitals in assigned departments',
            'vitals.create' => 'Record patient vitals',
            'vitals.update' => 'Edit patient vitals',
            'vitals.delete' => 'Delete vital records',

            // Consultation Management
            'consultations.view-all' => 'View all consultations system-wide',
            'consultations.view-dept' => 'View consultations in assigned departments',
            'consultations.view-own' => 'View own consultations only',
            'consultations.create' => 'Start new consultations',
            'consultations.update-any' => 'Update any consultation',
            'consultations.update-own' => 'Update own consultations only',
            'consultations.complete' => 'Complete consultations',
            'consultations.delete' => 'Delete consultations',

            // Diagnosis Management
            'diagnoses.view' => 'View patient diagnoses',
            'diagnoses.create' => 'Add diagnoses to consultations',
            'diagnoses.update' => 'Edit diagnoses',
            'diagnoses.delete' => 'Delete diagnoses',

            // Prescription Management
            'prescriptions.view' => 'View prescriptions',
            'prescriptions.create' => 'Create prescriptions',
            'prescriptions.update' => 'Edit prescriptions',
            'prescriptions.delete' => 'Delete prescriptions',

            // Lab Order Management
            'lab-orders.view-all' => 'View all lab orders',
            'lab-orders.view-dept' => 'View lab orders for assigned departments',
            'lab-orders.create' => 'Create lab orders',
            'lab-orders.update' => 'Update lab orders',
            'lab-orders.delete' => 'Delete lab orders',

            // Billing Management
            'billing.view-all' => 'View all patient bills',
            'billing.view-dept' => 'View bills for assigned departments',
            'billing.create' => 'Generate patient bills',
            'billing.update' => 'Update billing information',
            'billing.delete' => 'Delete bills',

            // Department Management
            'departments.view' => 'View departments',
            'departments.create' => 'Create departments',
            'departments.update' => 'Update departments',
            'departments.delete' => 'Delete departments',
            'departments.assign-users' => 'Assign users to departments',

            // User Management
            'users.view' => 'View users',
            'users.create' => 'Create new users',
            'users.update' => 'Update user information',
            'users.delete' => 'Delete users',
            'users.manage-permissions' => 'Manage user permissions',

            // System Administration
            'system.admin' => 'Full system administration access',
            'system.reports' => 'Access system reports',
            'system.settings' => 'Manage system settings',
        ];

        foreach ($permissions as $name => $description) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // Create or get roles
        $receptionist = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Receptionist',
            'guard_name' => 'web',
        ]);

        $nurse = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Nurse',
            'guard_name' => 'web',
        ]);

        $doctor = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Doctor',
            'guard_name' => 'web',
        ]);

        $admin = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        // Clear existing permissions and assign new ones
        $receptionist->syncPermissions([
            'patients.view-dept',
            'patients.create',
            'patients.update',
            'checkins.view-dept',
            'checkins.create',
            'checkins.update',
        ]);

        $nurse->syncPermissions([
            'patients.view-dept',
            'patients.update',
            'checkins.view-dept',
            'checkins.update',
            'vitals.view-dept',
            'vitals.create',
            'vitals.update',
            'consultations.view-dept',
        ]);

        $doctor->syncPermissions([
            'patients.view-dept',
            'patients.update',
            'checkins.view-dept',
            'vitals.view-dept',
            'vitals.create',
            'vitals.update',
            'consultations.view-dept',
            'consultations.create',
            'consultations.update-any',
            'consultations.complete',
            'diagnoses.view',
            'diagnoses.create',
            'diagnoses.update',
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.update',
            'lab-orders.view-dept',
            'lab-orders.create',
            'lab-orders.update',
            'billing.view-dept',
        ]);

        // Admin gets ALL permissions automatically
        $admin->syncPermissions(\Spatie\Permission\Models\Permission::all());

        // Ensure admin user has admin role with all permissions
        $adminUser = \App\Models\User::where('email', 'admin@hms.com')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['Admin']);
            $adminUser->syncPermissions(\Spatie\Permission\Models\Permission::all());
        }
    }
}
