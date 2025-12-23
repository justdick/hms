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
        // Core seeders (always run)
        $this->call([
            PermissionSeeder::class,
            DepartmentSeeder::class,
            PaymentMethodSeeder::class,
        ]);

        // Test data only in local/dev environment
        if (app()->environment('local', 'development', 'testing')) {
            $this->call([
                TestDataSeeder::class,
            ]);
        }

        // Uncomment as needed:
        // MinorProcedureTypesSeeder::class,
        // LabServiceSeeder::class,
        // PharmacySeeder::class,
        // DiagnosisSeeder::class,
        // BillingServiceSeeder::class,
        // BillingSeeder::class,
        // WardSeeder::class,
        // SystemConfigurationSeeder::class,
    }
}
