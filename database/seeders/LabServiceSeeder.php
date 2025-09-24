<?php

namespace Database\Seeders;

use App\Models\LabService;
use Illuminate\Database\Seeder;

class LabServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labServices = [
            // Hematology
            [
                'name' => 'Complete Blood Count (CBC)',
                'code' => 'CBC001',
                'category' => 'Hematology',
                'description' => 'Comprehensive blood analysis including RBC, WBC, and platelet counts',
                'price' => 150.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '2-4 hours',
                'is_active' => true,
            ],
            [
                'name' => 'Hemoglobin A1C',
                'code' => 'HBA1C001',
                'category' => 'Hematology',
                'description' => 'Average blood glucose levels over past 2-3 months',
                'price' => 120.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '3-4 hours',
                'is_active' => true,
            ],

            // Chemistry
            [
                'name' => 'Basic Metabolic Panel',
                'code' => 'BMP001',
                'category' => 'Chemistry',
                'description' => 'Basic blood chemistry including glucose, electrolytes, kidney function',
                'price' => 120.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '1-2 hours',
                'is_active' => true,
            ],
            [
                'name' => 'Comprehensive Metabolic Panel',
                'code' => 'CMP001',
                'category' => 'Chemistry',
                'description' => 'Extended blood chemistry including liver function tests',
                'price' => 180.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '2-3 hours',
                'is_active' => true,
            ],
            [
                'name' => 'Lipid Panel',
                'code' => 'LIPID001',
                'category' => 'Chemistry',
                'description' => 'Cholesterol and triglyceride levels',
                'price' => 160.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '2-3 hours',
                'is_active' => true,
            ],
            [
                'name' => 'Liver Function Test',
                'code' => 'LFT001',
                'category' => 'Chemistry',
                'description' => 'Comprehensive liver enzyme and function assessment',
                'price' => 200.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '3-4 hours',
                'is_active' => true,
            ],

            // Endocrinology
            [
                'name' => 'Thyroid Function Test',
                'code' => 'TFT001',
                'category' => 'Endocrinology',
                'description' => 'TSH, T3, T4 levels',
                'price' => 250.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '4-6 hours',
                'is_active' => true,
            ],

            // Urinalysis
            [
                'name' => 'Urinalysis Complete',
                'code' => 'URINE001',
                'category' => 'Urinalysis',
                'description' => 'Complete urine examination including microscopy',
                'price' => 75.00,
                'sample_type' => 'Urine',
                'turnaround_time' => '1 hour',
                'is_active' => true,
            ],

            // Radiology
            [
                'name' => 'Chest X-Ray (PA)',
                'code' => 'XRAY001',
                'category' => 'Radiology',
                'description' => 'Posterior-anterior chest radiograph',
                'price' => 300.00,
                'sample_type' => 'None',
                'turnaround_time' => '30 minutes',
                'is_active' => true,
            ],
            [
                'name' => 'Chest X-Ray (PA & Lateral)',
                'code' => 'XRAY002',
                'category' => 'Radiology',
                'description' => 'Two-view chest radiograph',
                'price' => 400.00,
                'sample_type' => 'None',
                'turnaround_time' => '45 minutes',
                'is_active' => true,
            ],

            // Cardiology
            [
                'name' => 'Electrocardiogram (ECG)',
                'code' => 'ECG001',
                'category' => 'Cardiology',
                'description' => '12-lead ECG',
                'price' => 100.00,
                'sample_type' => 'None',
                'turnaround_time' => '15 minutes',
                'is_active' => true,
            ],

            // Microbiology
            [
                'name' => 'Blood Culture',
                'code' => 'CULTURE001',
                'category' => 'Microbiology',
                'description' => 'Blood culture for bacterial infection',
                'price' => 350.00,
                'sample_type' => 'Blood',
                'turnaround_time' => '24-48 hours',
                'is_active' => true,
            ],
        ];

        foreach ($labServices as $service) {
            LabService::create($service);
        }
    }
}
