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
            'patients.view' => 'View patients',
            'patients.view-all' => 'View all patients system-wide',
            'patients.view-dept' => 'View patients in assigned departments',
            'patients.view-medical-history' => 'View patient medical history',
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
            'vitals.edit-timestamp' => 'Edit vital signs recorded date and time',
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
            'consultations.filter-by-date' => 'Filter consultation queue by date (view historical check-ins)',

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
            'billing.waive-charges' => 'Waive patient charges',
            'billing.adjust-charges' => 'Adjust charge amounts',
            'billing.emergency-override' => 'Override service access requirements',
            'billing.cancel-charges' => 'Cancel charges',
            'billing.view-audit-trail' => 'View billing audit trail',

            // Granular Billing Permissions (Revenue Collector & Finance Officer)
            'billing.collect' => 'Process payments and view own collections',
            'billing.override' => 'Create service overrides for patients',
            'billing.reconcile' => 'Perform cash reconciliation',
            'billing.reports' => 'Access financial reports',
            'billing.statements' => 'Generate patient statements',
            'billing.manage-credit' => 'Add or remove patient credit tags',
            'billing.void' => 'Void payments',
            'billing.refund' => 'Process refunds',
            'billing.refund-deposits' => 'Refund advance deposits',

            // Department Management
            'departments.view' => 'View departments',
            'departments.create' => 'Create departments',
            'departments.update' => 'Update departments',
            'departments.delete' => 'Delete departments',
            'departments.assign-users' => 'Assign users to departments',

            // User Management
            'users.view' => 'View users',
            'users.view-all' => 'View all users system-wide',
            'users.create' => 'Create new users',
            'users.update' => 'Update user information',
            'users.delete' => 'Delete users',
            'users.reset-password' => 'Reset user passwords',
            'users.manage-permissions' => 'Manage user permissions',
            'users.assign-direct-permissions' => 'Assign direct permissions to users',

            // Role Management
            'roles.view-all' => 'View all roles',
            'roles.create' => 'Create new roles',
            'roles.update' => 'Update role information and permissions',
            'roles.delete' => 'Delete roles',

            // Pharmacy Management
            'pharmacy.view' => 'View pharmacy dashboard',
            'pharmacy.manage' => 'Manage pharmacy operations',

            // Drug Management
            'drugs.view' => 'View drugs and inventory',
            'drugs.create' => 'Add new drugs',
            'drugs.update' => 'Update drug information',
            'drugs.delete' => 'Delete drugs',
            'drugs.manage-batches' => 'Manage drug batches',
            'drugs.manage-nhis-settings' => 'Manage NHIS claim settings for drugs (e.g., claim qty as 1)',

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
            'admissions.view-transfers' => 'View ward transfer history',

            // Nursing Notes Management
            'nursing-notes.view' => 'View nursing notes',
            'nursing-notes.create' => 'Create nursing notes',
            'nursing-notes.update' => 'Update own nursing notes (within 24 hours)',
            'nursing-notes.delete' => 'Delete own nursing notes (within 2 hours)',

            // Ward Rounds Management
            'ward_rounds.view' => 'View ward rounds',
            'ward_rounds.create' => 'Record ward rounds',
            'ward_rounds.update' => 'Update own ward rounds (within 24 hours)',
            'ward_rounds.update-any' => 'Update any ward round (for supervisors)',
            'ward_rounds.delete' => 'Delete ward rounds (admin only)',
            'ward_rounds.restore' => 'Restore deleted ward rounds',
            'ward_rounds.force_delete' => 'Permanently delete ward rounds',

            // Medication Administration Management
            'medications.view' => 'View medication schedules',
            'medications.administer' => 'Administer medications',
            'medications.delete' => 'Delete medication administrations (within 3 days)',
            'medications.hold' => 'Hold medication administration',
            'medications.refuse' => 'Mark medication as refused',
            'medications.omit' => 'Omit medication administration',
            'medications.edit-timestamp' => 'Edit medication administration date and time',

            // Minor Procedures Management
            'minor-procedures.view-dept' => 'View minor procedures queue in assigned departments',
            'minor-procedures.perform' => 'Perform and document minor procedures',
            'minor-procedures.view-all' => 'View all minor procedures system-wide (admin)',

            // Minor Procedure Types Configuration
            'minor-procedures.view-types' => 'View procedure types configuration',
            'minor-procedures.create-types' => 'Create new procedure types',
            'minor-procedures.update-types' => 'Update existing procedure types',
            'minor-procedures.delete-types' => 'Delete procedure types',

            // System Administration
            'system.admin' => 'Full system administration access',
            'system.reports' => 'Access system reports',
            'system.settings' => 'Manage system settings',

            // Insurance Management
            'insurance.view' => 'View insurance providers and plans',
            'insurance.manage' => 'Manage insurance providers and plans',
            'insurance.vet-claims' => 'Vet and approve insurance claims',
            'insurance.view-batches' => 'View claim batches',
            'insurance.manage-batches' => 'Manage claim batches and submissions',
            'insurance.submit-batches' => 'Submit claim batches to NHIA',
            'insurance.export-batches' => 'Export claim batches to XML',
            'insurance.record-batch-responses' => 'Record NHIA responses for batches',
            'insurance.view-reports' => 'View insurance reports and analytics',

            // NHIS Tariff Management
            'nhis-tariffs.view' => 'View NHIS tariffs',
            'nhis-tariffs.manage' => 'Manage NHIS tariffs (create, update, delete, import)',

            // NHIS Mapping Management
            'nhis-mappings.view' => 'View NHIS item mappings',
            'nhis-mappings.manage' => 'Manage NHIS item mappings (create, delete, import)',

            // NHIS Settings Management
            'nhis-settings.view' => 'View NHIS verification settings',
            'nhis-settings.manage' => 'Manage NHIS verification settings',

            // G-DRG Tariff Management
            'gdrg-tariffs.view' => 'View G-DRG tariffs',
            'gdrg-tariffs.manage' => 'Manage G-DRG tariffs (create, update, delete, import)',

            // Backup Management
            'backups.view' => 'View database backups',
            'backups.create' => 'Create database backups',
            'backups.delete' => 'Delete database backups',
            'backups.restore' => 'Restore database from backups',
            'backups.manage-settings' => 'Manage backup settings (schedule, retention, Google Drive)',

            // Theme Settings Management
            'settings.view-theme' => 'View application theme settings',
            'settings.manage-theme' => 'Manage application theme and branding settings',

            // Pricing Dashboard Management
            'pricing.view' => 'View unified pricing dashboard',
            'pricing.edit' => 'Edit prices and copay amounts in pricing dashboard',

            // Investigations Management (Imaging/Radiology)
            'investigations.order' => 'Order imaging studies during consultation',
            'investigations.upload-external' => 'Upload external imaging studies from other facilities',

            // Radiology Management
            'radiology.view-worklist' => 'View radiology worklist of pending imaging orders',
            'radiology.upload' => 'Upload images for imaging orders',
            'radiology.report' => 'Enter radiology reports for imaging orders',
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
            'checkins.create', // Nurses can check-in patients
            'checkins.update',
            'checkins.cancel', // Nurses can cancel check-ins
            'vitals.view-dept',
            'vitals.create',
            'vitals.update',
            'consultations.view-dept',
            'wards.view',
            'admissions.view',
            // Note: admissions.discharge is NOT given to all nurses by default
            // Admin can assign it to specific nurses (in-charges, duty leaders)
            'nursing-notes.view',
            'nursing-notes.create',
            'nursing-notes.update',
            'nursing-notes.delete',
            'ward_rounds.view',
            'medications.view',
            'medications.administer',
            'medications.delete',
            'medications.hold',
            'medications.refuse',
            'medications.omit',
            'manage prescriptions', // Nurses can discontinue prescriptions
            'minor-procedures.view-dept',
            'minor-procedures.perform',
        ]);

        $doctor->syncPermissions([
            'patients.view-dept',
            'patients.view-medical-history',
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
            'ward_rounds.update-any',
            'medications.view',
            'medications.administer',
            'manage prescriptions', // Doctors can discontinue prescriptions
            'investigations.order', // Doctors can order imaging studies
            'investigations.upload-external', // Doctors can upload external imaging
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

        // Create Cashier role (Revenue Collector)
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
            'billing.collect', // Process payments and view own collections
            'billing.waive-charges',
            'billing.adjust-charges',
            'billing.emergency-override',
            'billing.cancel-charges',
            'billing.view-audit-trail',
        ]);

        // Create Finance Officer role (Accountant)
        $financeOfficer = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Finance Officer',
            'guard_name' => 'web',
        ]);

        $financeOfficer->syncPermissions([
            'patients.view-all',
            'checkins.view-all',
            'billing.view-all',
            'billing.collect', // Can also collect payments
            'billing.override', // Create service overrides
            'billing.reconcile', // Perform cash reconciliation
            'billing.reports', // Access financial reports
            'billing.statements', // Generate patient statements
            'billing.manage-credit', // Manage patient credit tags
            'billing.void', // Void payments
            'billing.refund', // Process refunds
            'billing.view-audit-trail',
        ]);

        // Create Insurance Officer role
        $insuranceOfficer = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Insurance Officer',
            'guard_name' => 'web',
        ]);

        $insuranceOfficer->syncPermissions([
            'patients.view-all',
            'checkins.view-all',
            'insurance.view',
            'insurance.manage',
            'insurance.vet-claims',
            'insurance.manage-batches',
            'insurance.view-reports',
            'nhis-tariffs.view',
            'nhis-tariffs.manage',
            'nhis-mappings.view',
            'nhis-mappings.manage',
            'gdrg-tariffs.view',
            'gdrg-tariffs.manage',
            'nhis-settings.view',
            'nhis-settings.manage',
            'pricing.view',
            'pricing.edit',
            'drugs.view',
            'drugs.manage-nhis-settings',
        ]);

        // Create Radiologist role
        $radiologist = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Radiologist',
            'guard_name' => 'web',
        ]);

        $radiologist->syncPermissions([
            'patients.view-all',
            'radiology.view-worklist',
            'radiology.upload',
            'radiology.report',
            'lab-orders.view-all',
            'lab-orders.update',
        ]);

        // Create Radiology Technician role
        $radiologyTech = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Radiology Technician',
            'guard_name' => 'web',
        ]);

        $radiologyTech->syncPermissions([
            'patients.view-all',
            'radiology.view-worklist',
            'radiology.upload',
            'lab-orders.view-all',
            'lab-orders.update',
        ]);

        // Admin gets ALL permissions automatically
        $admin->syncPermissions(\Spatie\Permission\Models\Permission::all());

        // Create Admin Support role - has all permissions except system admin and direct permission assignment
        $adminSupport = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'Admin Support',
            'guard_name' => 'web',
        ]);

        // Admin Support gets all permissions except system.admin and users.assign-direct-permissions
        $adminSupportPermissions = \Spatie\Permission\Models\Permission::whereNotIn('name', [
            'system.admin',
            'users.assign-direct-permissions',
        ])->get();
        $adminSupport->syncPermissions($adminSupportPermissions);

        // Ensure admin user has admin role with all permissions
        $adminUser = \App\Models\User::where('username', 'admin')->first();
        if ($adminUser) {
            $adminUser->syncRoles(['Admin']);
            $adminUser->syncPermissions(\Spatie\Permission\Models\Permission::all());

            // Assign all active departments to admin
            $allDepartments = \App\Models\Department::where('is_active', true)->pluck('id');
            if ($allDepartments->isNotEmpty()) {
                $adminUser->departments()->sync($allDepartments);
            }
        }
    }
}
