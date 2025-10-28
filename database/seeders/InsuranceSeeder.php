<?php

namespace Database\Seeders;

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use Illuminate\Database\Seeder;

class InsuranceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Insurance Providers
        $vetInsurance = InsuranceProvider::create([
            'name' => 'VET Insurance',
            'code' => 'VET001',
            'contact_person' => 'Mr. Kwame Mensah',
            'phone' => '+233 24 123 4567',
            'email' => 'claims@vetinsurance.gh',
            'address' => 'Accra, Ghana',
            'claim_submission_method' => 'online',
            'payment_terms_days' => 30,
            'is_active' => true,
            'notes' => 'Primary insurance provider for government employees',
        ]);

        $nhis = InsuranceProvider::create([
            'name' => 'NHIS',
            'code' => 'NHIS001',
            'contact_person' => 'Mrs. Abena Osei',
            'phone' => '+233 30 234 5678',
            'email' => 'claims@nhis.gov.gh',
            'address' => 'Accra, Ghana',
            'claim_submission_method' => 'manual',
            'payment_terms_days' => 60,
            'is_active' => true,
            'notes' => 'National Health Insurance Scheme',
        ]);

        $aarHealth = InsuranceProvider::create([
            'name' => 'AAR Health Insurance',
            'code' => 'AAR001',
            'contact_person' => 'Mr. Kofi Agyeman',
            'phone' => '+233 20 345 6789',
            'email' => 'claims@aarhealth.com',
            'address' => 'Accra, Ghana',
            'claim_submission_method' => 'api',
            'payment_terms_days' => 45,
            'is_active' => true,
            'notes' => 'Private health insurance provider',
        ]);

        // Create Insurance Plans
        $vetGold = InsurancePlan::create([
            'insurance_provider_id' => $vetInsurance->id,
            'plan_name' => 'Gold Plan',
            'plan_code' => 'GOLD',
            'plan_type' => 'corporate',
            'coverage_type' => 'comprehensive',
            'annual_limit' => null, // Unlimited
            'visit_limit' => null,
            'default_copay_percentage' => 0.00, // 100% coverage
            'requires_referral' => false,
            'is_active' => true,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYears(2),
            'description' => '100% coverage for all services',
        ]);

        $nhiStandard = InsurancePlan::create([
            'insurance_provider_id' => $nhis->id,
            'plan_name' => 'Standard Plan',
            'plan_code' => 'STD',
            'plan_type' => 'individual',
            'coverage_type' => 'comprehensive',
            'annual_limit' => 50000.00,
            'visit_limit' => 20,
            'default_copay_percentage' => 10.00, // 90% coverage
            'requires_referral' => false,
            'is_active' => true,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYears(2),
            'description' => '90% coverage with GHS 50,000 annual limit',
        ]);

        $aarPremium = InsurancePlan::create([
            'insurance_provider_id' => $aarHealth->id,
            'plan_name' => 'Premium Plan',
            'plan_code' => 'PREM',
            'plan_type' => 'family',
            'coverage_type' => 'comprehensive',
            'annual_limit' => 100000.00,
            'visit_limit' => null,
            'default_copay_percentage' => 20.00, // 80% coverage
            'requires_referral' => true,
            'is_active' => true,
            'effective_from' => now()->subYear(),
            'effective_to' => now()->addYears(2),
            'description' => '80% coverage for outpatient, 100% for inpatient',
        ]);

        // Create Coverage Rules for VET Gold (100% coverage for all)
        $coverageCategories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
        foreach ($coverageCategories as $category) {
            InsuranceCoverageRule::create([
                'insurance_plan_id' => $vetGold->id,
                'coverage_category' => $category,
                'item_code' => null,
                'item_description' => "All {$category} services",
                'is_covered' => true,
                'coverage_type' => 'full',
                'coverage_value' => 100.00,
                'patient_copay_percentage' => 0.00,
                'max_quantity_per_visit' => null,
                'max_amount_per_visit' => null,
                'requires_preauthorization' => false,
                'is_active' => true,
                'effective_from' => now()->subYear(),
                'effective_to' => null,
            ]);
        }

        // Create Coverage Rules for NHIS Standard (90% coverage)
        foreach ($coverageCategories as $category) {
            InsuranceCoverageRule::create([
                'insurance_plan_id' => $nhiStandard->id,
                'coverage_category' => $category,
                'item_code' => null,
                'item_description' => "All {$category} services",
                'is_covered' => true,
                'coverage_type' => 'percentage',
                'coverage_value' => 90.00,
                'patient_copay_percentage' => 10.00,
                'max_quantity_per_visit' => null,
                'max_amount_per_visit' => null,
                'requires_preauthorization' => false,
                'is_active' => true,
                'effective_from' => now()->subYear(),
                'effective_to' => null,
            ]);
        }

        // Create Coverage Rules for AAR Premium (80% coverage)
        foreach ($coverageCategories as $category) {
            InsuranceCoverageRule::create([
                'insurance_plan_id' => $aarPremium->id,
                'coverage_category' => $category,
                'item_code' => null,
                'item_description' => "All {$category} services",
                'is_covered' => true,
                'coverage_type' => 'percentage',
                'coverage_value' => 80.00,
                'patient_copay_percentage' => 20.00,
                'max_quantity_per_visit' => null,
                'max_amount_per_visit' => null,
                'requires_preauthorization' => $category === 'procedure',
                'is_active' => true,
                'effective_from' => now()->subYear(),
                'effective_to' => null,
            ]);
        }

        // Create sample patient insurance records for existing patients if any
        $patients = Patient::limit(5)->get();
        if ($patients->count() > 0) {
            foreach ($patients as $index => $patient) {
                $plan = match ($index % 3) {
                    0 => $vetGold,
                    1 => $nhiStandard,
                    default => $aarPremium,
                };

                PatientInsurance::create([
                    'patient_id' => $patient->id,
                    'insurance_plan_id' => $plan->id,
                    'membership_id' => str_pad($index + 13879200, 8, '0', STR_PAD_LEFT),
                    'policy_number' => 'POL'.str_pad($index + 1000, 7, '0', STR_PAD_LEFT),
                    'folder_id_prefix' => substr($patient->patient_number, 0, 2),
                    'is_dependent' => false,
                    'principal_member_name' => null,
                    'relationship_to_principal' => 'self',
                    'coverage_start_date' => now()->subMonths(6),
                    'coverage_end_date' => now()->addYear(),
                    'status' => 'active',
                    'card_number' => 'CARD'.str_pad($index + 1000, 10, '0', STR_PAD_LEFT),
                    'notes' => 'Sample insurance coverage',
                ]);
            }
        }
    }
}
