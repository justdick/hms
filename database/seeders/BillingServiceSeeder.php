<?php

namespace Database\Seeders;

use App\Models\BillingService;
use Illuminate\Database\Seeder;

class BillingServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $billingServices = [
            // Consultation Services
            [
                'service_type' => 'consultation',
                'service_code' => 'CONSULT_GENERAL',
                'service_name' => 'General Consultation',
                'base_price' => 100.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'consultation',
                'service_code' => 'CONSULT_SPECIALIST',
                'service_name' => 'Specialist Consultation',
                'base_price' => 150.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'consultation',
                'service_code' => 'CONSULT_FOLLOWUP',
                'service_name' => 'Follow-up Consultation',
                'base_price' => 75.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'consultation',
                'service_code' => 'CONSULT_EMERGENCY',
                'service_name' => 'Emergency Consultation',
                'base_price' => 200.00,
                'is_active' => true,
            ],

            // Common Lab Tests (matching LabService prices)
            [
                'service_type' => 'lab_test',
                'service_code' => 'LAB_CBC',
                'service_name' => 'Complete Blood Count (CBC)',
                'base_price' => 150.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'lab_test',
                'service_code' => 'LAB_BMP',
                'service_name' => 'Basic Metabolic Panel',
                'base_price' => 120.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'lab_test',
                'service_code' => 'LAB_LIPID',
                'service_name' => 'Lipid Panel',
                'base_price' => 160.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'lab_test',
                'service_code' => 'LAB_URINE',
                'service_name' => 'Urinalysis Complete',
                'base_price' => 75.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'lab_test',
                'service_code' => 'LAB_XRAY',
                'service_name' => 'Chest X-Ray',
                'base_price' => 300.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'lab_test',
                'service_code' => 'LAB_ECG',
                'service_name' => 'Electrocardiogram (ECG)',
                'base_price' => 100.00,
                'is_active' => true,
            ],

            // Common Procedures
            [
                'service_type' => 'procedure',
                'service_code' => 'PROC_INJECTION',
                'service_name' => 'Injection Administration',
                'base_price' => 25.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'procedure',
                'service_code' => 'PROC_WOUND_DRESS',
                'service_name' => 'Wound Dressing',
                'base_price' => 50.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'procedure',
                'service_code' => 'PROC_SUTURE',
                'service_name' => 'Suturing (Simple)',
                'base_price' => 150.00,
                'is_active' => true,
            ],
            [
                'service_type' => 'procedure',
                'service_code' => 'PROC_IV_CANNULATION',
                'service_name' => 'IV Cannulation',
                'base_price' => 75.00,
                'is_active' => true,
            ],

            // Medication Administration
            [
                'service_type' => 'medication',
                'service_code' => 'MED_ADMINISTRATION',
                'service_name' => 'Medication Administration Fee',
                'base_price' => 15.00,
                'is_active' => true,
            ],
        ];

        foreach ($billingServices as $service) {
            BillingService::create($service);
        }
    }
}
