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
            'checkins.view-any-date' => 'View check-ins from any date (historical)',
            'checkins.view-any-department' => 'View check-ins from other departments',
            'checkins.create' => 'Check-in patients',
            'checkins.update' => 'Update check-in status',
            'checkins.update-date' => 'Update check-in date (including completed check-ins)',
            'checkins.cancel' => 'Cancel check-ins and void unpaid charges',
            'checkins.delete' => 'Delete check-in records',

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
            'manage prescriptions' => 'Manage prescriptions (discontinue, etc.)',

            // Lab Order Management
            'lab-orders.view-all' => 'View all lab orders',
            'lab-orders.view-dept' => 'View lab orders for assigned departments',
            'lab-orders.create' => 'Create lab orders',
            'lab-orders.update' => 'Update lab orders',
            'lab-orders.delete' => 'Delete lab orders',

            // Lab Service Management
            'lab-services.view' => 'View lab services',
            'lab-services.create' => 'Create new lab services',
            'lab-services.update' => 'Update lab services',
            'lab-services.delete' => 'Delete lab services',
            'configure lab parameters' => 'Configure dynamic test parameters for lab services',

            // Billing Management
            'billing.view-all' => 'View all patient bills',
            'billing.view-dept' => 'View bills for assigned departments',
            'billing.create' => 'Generate patient bills',
            'billing.update' => 'Update billing information',
            'billing.delete' => 'Delete bills',
            'billing.configure' => 'Configure billing settings and rules',

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

            // Pharmacy Management
            'pharmacy.view' => 'View pharmacy dashboard',
            'pharmacy.manage' => 'Manage pharmacy operations',

            // Drug Management
            'drugs.view' => 'View drugs and inventory',
            'drugs.create' => 'Add new drugs',
            'drugs.update' => 'Update drug information',
            'drugs.delete' => 'Delete drugs',
            'drugs.manage-batches' => 'Manage drug batches',

            // Dispensing Management
            'dispensing.view' => 'View dispensing records',
            'dispensing.view-all' => 'View all dispensing records',
            'dispensing.review' => 'Review prescriptions (Touchpoint 1)',
            'dispensing.adjust-quantity' => 'Adjust prescription quantities during review',
            'dispensing.mark-external' => 'Mark prescriptions as externally dispensed',
            'dispensing.process' => 'Process drug dispensing (Touchpoint 2)',
            'dispensing.partial' => 'Process partial dispensing',
            'dispensing.override-payment' => 'Override payment requirements for emergency dispensing',
            'dispensing.history' => 'View dispensing history',
            'dispensing.reports' => 'Generate dispensing reports',

            // Inventory Management
            'inventory.view' => 'View inventory levels',
            'inventory.manage' => 'Manage inventory stock',
            'inventory.reports' => 'Generate inventory reports',

            // Ward Management
            'wards.view' => 'View wards',
            'wards.create' => 'Create wards',
            'wards.update' => 'Update ward information',
            'wards.delete' => 'Delete wards',
            'wards.manage-beds' => 'Manage ward beds',

            // Patient Admission Management
            'admissions.view' => 'View patient admissions',
            'admissions.create' => 'Admit patients',
            'admissions.update' => 'Update admission information',
            'admissions.discharge' => 'Discharge patients',
            'admissions.transfer' => 'Transfer patients between wards',

            // Nursing Notes Management
            'nursing-notes.view' => 'View nursing notes',
            'nursing-notes.create' => 'Create nursing notes',
            'nursing-notes.update' => 'Update own nursing notes (within 24 hours)',
            'nursing-notes.delete' => 'Delete own nursing notes (within 2 hours)',

            // Ward Rounds Management
            'ward_rounds.view' => 'View ward rounds',
            'ward_rounds.create' => 'Record ward rounds',
            'ward_rounds.update' => 'Update own ward rounds (within 24 hours)',
            'ward_rounds.delete' => 'Delete ward rounds (admin only)',
            'ward_rounds.restore' => 'Restore deleted ward rounds',
            'ward_rounds.force_delete' => 'Permanently delete ward rounds',

            // Medication Administration Management
            'view medication administrations' => 'View medication schedules',
            'administer medications' => 'Administer medications',
            'delete medication administrations' => 'Delete medication administrations',
            'medications.view' => 'View medication schedules (legacy)',
            'medications.administer' => 'Administer medications (legacy)',
            'medications.hold' => 'Hold medication administration',
            'medications.refuse' => 'Mark medication as refused',
            'medications.omit' => 'Omit medication administration',

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
            'checkins.cancel', // Receptionists can cancel check-ins
        ]);

        $nurse->syncPermissions([
            'patients.view-dept',
            'patients.update',
            'checkins.view-dept',
            'checkins.update',
            'checkins.cancel', // Nurses can cancel check-ins
            'vitals.view-dept',
            'vitals.create',
            'vitals.update',
            'consultations.view-dept',
            'wards.view',
            'admissions.view',
            'nursing-notes.view',
            'nursing-notes.create',
            'nursing-notes.update',
            'nursing-notes.delete',
            'ward_rounds.view',
            'view medication administrations',
            'administer medications',
            'delete medication administrations',
            'medications.view',
            'medications.administer',
            'medications.hold',
            'medications.refuse',
            'medications.omit',
            'manage prescriptions', // Nurses can discontinue prescriptions
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
            'pharmacy.view',
            'drugs.view',
            'wards.view',
            'admissions.view',
            'admissions.create',
            'admissions.update',
            'admissions.discharge',
            'admissions.transfer',
            'nursing-notes.view',
            'ward_rounds.view',
            'ward_rounds.create',
            'ward_rounds.update',
            'view medication administrations',
            'administer medications',
            'medications.view',
            'manage prescriptions', // Doctors can discontinue prescriptions
        ]);

        // Create Pharmacist role
        $pharmacist = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Pharmacist',
            'guard_name' => 'web',
        ]);

        $pharmacist->syncPermissions([
            'patients.view-dept',
            'pharmacy.view',
            'pharmacy.manage',
            'drugs.view',
            'drugs.create',
            'drugs.update',
            'drugs.manage-batches',
            'dispensing.view',
            'dispensing.view-all',
            'dispensing.review',
            'dispensing.adjust-quantity',
            'dispensing.mark-external',
            'dispensing.process',
            'dispensing.partial',
            'dispensing.history',
            'dispensing.reports',
            'inventory.view',
            'inventory.manage',
            'inventory.reports',
            'prescriptions.view',
        ]);

        // Create Cashier role
        $cashier = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Cashier',
            'guard_name' => 'web',
        ]);

        $cashier->syncPermissions([
            'patients.view-all',
            'checkins.view-all',
            'billing.view-all',
            'billing.create',
            'billing.update',
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
