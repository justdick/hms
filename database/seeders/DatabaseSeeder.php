<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default user
        // User::firstOrCreate(
        //     ['email' => 'test@example.com'],
        //     [
        //         'name' => 'Test User',
        //         'password' => Hash::make('password'),
        //         'email_verified_at' => now(),
        //     ]
        // );

        // Seed consultation module data
        $this->call([
            PermissionSeeder::class,
            DepartmentSeeder::class,
            MinorProceduresDepartmentSeeder::class,
            MinorProcedureTypesSeeder::class,
            TestDataSeeder::class,
            PaymentMethodSeeder::class,
            // LabServiceSeeder::class,
            // PharmacySeeder::class,
            // DiagnosisSeeder::class,
            // BillingServiceSeeder::class,
            // BillingSeeder::class,
            // PreviousConsultationsSeeder::class,
            // WardSeeder::class,
            // SystemConfigurationSeeder::class,
            // InsuranceReportsSeeder::class,

        ]);
    }
}
