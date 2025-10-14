<?php

namespace Database\Seeders;

use App\Models\BillingConfiguration;
use App\Models\Department;
use App\Models\DepartmentBilling;
use App\Models\ServiceChargeRule;
use App\Models\WardBillingTemplate;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBillingConfigurations();
        $this->seedDepartmentBillings();
        $this->seedServiceChargeRules();
        $this->seedWardBillingTemplates();
    }

    private function seedBillingConfigurations(): void
    {
        $configurations = [
            [
                'key' => 'auto_billing_enabled',
                'category' => 'general',
                'value' => true,
                'description' => 'Enable automatic billing when patients check into departments',
            ],
            [
                'key' => 'emergency_override_global',
                'category' => 'general',
                'value' => true,
                'description' => 'Allow emergency override for all billing requirements',
            ],
            [
                'key' => 'default_payment_grace_period',
                'category' => 'general',
                'value' => 30,
                'description' => 'Default grace period for payments in minutes',
            ],
            [
                'key' => 'currency',
                'category' => 'general',
                'value' => 'GHS',
                'description' => 'System currency code',
            ],
            [
                'key' => 'currency_symbol',
                'category' => 'general',
                'value' => '₵',
                'description' => 'Currency symbol for display',
            ],
            [
                'key' => 'lab_result_visibility',
                'category' => 'laboratory',
                'value' => [
                    'hide_test_details_until_paid' => true,
                    'allow_sample_collection_unpaid' => false,
                    'show_pending_tests_to_lab_staff' => true,
                ],
                'description' => 'Laboratory billing and visibility rules',
            ],
            [
                'key' => 'ward_billing_rules',
                'category' => 'ward',
                'value' => [
                    'bed_assignment_requires_payment' => true,
                    'daily_charges_start_time' => '00:00',
                    'partial_day_billing' => true,
                ],
                'description' => 'Ward billing configuration rules',
            ],
            [
                'key' => 'pharmacy.require_payment_before_dispensing',
                'category' => 'pharmacy',
                'value' => true,
                'description' => 'Require payment before dispensing medications',
            ],
            [
                'key' => 'pharmacy.allow_partial_dispensing',
                'category' => 'pharmacy',
                'value' => true,
                'description' => 'Allow partial dispensing when stock is insufficient',
            ],
            [
                'key' => 'pharmacy.allow_external_prescriptions',
                'category' => 'pharmacy',
                'value' => true,
                'description' => 'Allow marking prescriptions as externally dispensed',
            ],
            [
                'key' => 'pharmacy.default_drug_markup_percentage',
                'category' => 'pharmacy',
                'value' => 20,
                'description' => 'Default markup percentage for drug pricing',
            ],
        ];

        foreach ($configurations as $config) {
            BillingConfiguration::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }

    private function seedDepartmentBillings(): void
    {
        $departmentMap = [
            'GEN_OPD' => [
                'department_name' => 'General OPD',
                'consultation_fee' => 50.00,
                'equipment_fee' => 10.00,
                'emergency_surcharge' => 25.00,
                'payment_required_before_consultation' => true,
                'emergency_override_allowed' => true,
                'payment_grace_period_minutes' => 30,
                'allow_partial_payment' => false,
                'payment_plan_available' => true,
            ],
            'EYE' => [
                'department_name' => 'Eye Clinic',
                'consultation_fee' => 70.00,
                'equipment_fee' => 20.00,
                'emergency_surcharge' => 35.00,
                'payment_required_before_consultation' => true,
                'emergency_override_allowed' => true,
                'payment_grace_period_minutes' => 20,
                'allow_partial_payment' => true,
                'payment_plan_available' => true,
            ],
            'ENT' => [
                'department_name' => 'ENT Clinic',
                'consultation_fee' => 80.00,
                'equipment_fee' => 25.00,
                'emergency_surcharge' => 40.00,
                'payment_required_before_consultation' => true,
                'emergency_override_allowed' => true,
                'payment_grace_period_minutes' => 20,
                'allow_partial_payment' => true,
                'payment_plan_available' => true,
            ],
            'MAT' => [
                'department_name' => 'Maternity',
                'consultation_fee' => 60.00,
                'equipment_fee' => 15.00,
                'emergency_surcharge' => 30.00,
                'payment_required_before_consultation' => true,
                'emergency_override_allowed' => true,
                'payment_grace_period_minutes' => 45,
                'allow_partial_payment' => true,
                'payment_plan_available' => true,
            ],
            'PED' => [
                'department_name' => 'Pediatrics',
                'consultation_fee' => 60.00,
                'equipment_fee' => 15.00,
                'emergency_surcharge' => 30.00,
                'payment_required_before_consultation' => true,
                'emergency_override_allowed' => true,
                'payment_grace_period_minutes' => 45,
                'allow_partial_payment' => true,
                'payment_plan_available' => true,
            ],
            'DENTAL' => [
                'department_name' => 'Dental Clinic',
                'consultation_fee' => 90.00,
                'equipment_fee' => 30.00,
                'emergency_surcharge' => 45.00,
                'payment_required_before_consultation' => true,
                'emergency_override_allowed' => false,
                'payment_grace_period_minutes' => 15,
                'allow_partial_payment' => false,
                'payment_plan_available' => true,
            ],
        ];

        foreach ($departmentMap as $code => $billingData) {
            $department = Department::where('code', $code)->first();

            if ($department) {
                DepartmentBilling::updateOrCreate(
                    ['department_id' => $department->id],
                    array_merge($billingData, [
                        'department_code' => $code,
                    ])
                );
            }
        }
    }

    private function seedServiceChargeRules(): void
    {
        $rules = [
            [
                'service_type' => 'consultation',
                'service_code' => null,
                'service_name' => 'General Consultation',
                'charge_timing' => 'on_checkin',
                'payment_required' => 'mandatory',
                'payment_timing' => 'immediate',
                'emergency_override_allowed' => true,
                'partial_payment_allowed' => false,
                'payment_plans_available' => true,
                'grace_period_days' => 0,
                'late_fees_enabled' => false,
                'service_blocking_enabled' => true,
                'hide_details_until_paid' => false,
            ],
            [
                'service_type' => 'laboratory',
                'service_code' => null,
                'service_name' => 'Laboratory Tests',
                'charge_timing' => 'before_service',
                'payment_required' => 'mandatory',
                'payment_timing' => 'immediate',
                'emergency_override_allowed' => true,
                'partial_payment_allowed' => false,
                'payment_plans_available' => false,
                'grace_period_days' => 0,
                'late_fees_enabled' => false,
                'service_blocking_enabled' => true,
                'hide_details_until_paid' => true,
            ],
            [
                'service_type' => 'pharmacy',
                'service_code' => null,
                'service_name' => 'Medication Dispensing',
                'charge_timing' => 'before_service',
                'payment_required' => 'mandatory',
                'payment_timing' => 'immediate',
                'emergency_override_allowed' => true,
                'partial_payment_allowed' => false,
                'payment_plans_available' => false,
                'grace_period_days' => 0,
                'late_fees_enabled' => false,
                'service_blocking_enabled' => true,
                'hide_details_until_paid' => false,
            ],
            [
                'service_type' => 'ward',
                'service_code' => 'bed_assignment',
                'service_name' => 'Bed Assignment',
                'charge_timing' => 'before_service',
                'payment_required' => 'mandatory',
                'payment_timing' => 'immediate',
                'emergency_override_allowed' => true,
                'partial_payment_allowed' => true,
                'payment_plans_available' => true,
                'grace_period_days' => 1,
                'late_fees_enabled' => true,
                'service_blocking_enabled' => true,
                'hide_details_until_paid' => false,
            ],
            [
                'service_type' => 'procedure',
                'service_code' => null,
                'service_name' => 'Medical Procedures',
                'charge_timing' => 'before_service',
                'payment_required' => 'mandatory',
                'payment_timing' => 'immediate',
                'emergency_override_allowed' => true,
                'partial_payment_allowed' => true,
                'payment_plans_available' => true,
                'grace_period_days' => 0,
                'late_fees_enabled' => false,
                'service_blocking_enabled' => true,
                'hide_details_until_paid' => false,
            ],
        ];

        foreach ($rules as $rule) {
            ServiceChargeRule::updateOrCreate(
                [
                    'service_type' => $rule['service_type'],
                    'service_code' => $rule['service_code'],
                ],
                $rule
            );
        }
    }

    private function seedWardBillingTemplates(): void
    {
        $templates = [
            [
                'service_name' => 'General Ward Bed',
                'service_code' => 'GENERAL_BED',
                'description' => 'Standard bed in general ward',
                'billing_type' => 'daily',
                'base_amount' => 150.00,
                'percentage_rate' => null,
                'calculation_rules' => [
                    'billing_starts' => 'admission_time',
                    'minimum_charge_hours' => 24,
                    'partial_day_billing' => true,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => ['general', 'medical'],
                'patient_category_rules' => [
                    'insurance' => ['discount_percentage' => 20],
                    'staff' => ['discount_percentage' => 50],
                    'emergency' => ['surcharge_percentage' => 0],
                ],
                'auto_trigger_conditions' => [
                    'trigger_on' => 'bed_assignment',
                    'auto_create_charges' => true,
                ],
                'payment_requirement' => 'immediate',
                'integration_points' => ['ward_management', 'admission'],
            ],
            [
                'service_name' => 'ICU Bed',
                'service_code' => 'ICU_BED',
                'description' => 'Intensive Care Unit bed with monitoring',
                'billing_type' => 'hourly',
                'base_amount' => 25.00,
                'percentage_rate' => null,
                'calculation_rules' => [
                    'billing_starts' => 'admission_time',
                    'minimum_charge_hours' => 4,
                    'round_up_hours' => true,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => ['icu', 'intensive_care'],
                'patient_category_rules' => [
                    'insurance' => ['discount_percentage' => 10],
                    'emergency' => ['surcharge_percentage' => 0],
                ],
                'auto_trigger_conditions' => [
                    'trigger_on' => 'bed_assignment',
                    'auto_create_charges' => true,
                ],
                'payment_requirement' => 'immediate',
                'integration_points' => ['ward_management', 'admission'],
            ],
            [
                'service_name' => 'Private Room',
                'service_code' => 'PRIVATE_ROOM',
                'description' => 'Private room with amenities',
                'billing_type' => 'daily',
                'base_amount' => 300.00,
                'percentage_rate' => null,
                'calculation_rules' => [
                    'billing_starts' => 'admission_time',
                    'minimum_charge_hours' => 24,
                    'partial_day_billing' => false,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => ['private', 'vip'],
                'patient_category_rules' => [
                    'staff' => ['discount_percentage' => 30],
                ],
                'auto_trigger_conditions' => [
                    'trigger_on' => 'bed_assignment',
                    'auto_create_charges' => true,
                ],
                'payment_requirement' => 'immediate',
                'integration_points' => ['ward_management', 'admission'],
            ],
            [
                'service_name' => 'Nursing Care Fee',
                'service_code' => 'NURSING_CARE',
                'description' => 'Daily nursing care and monitoring',
                'billing_type' => 'daily',
                'base_amount' => 50.00,
                'percentage_rate' => null,
                'calculation_rules' => [
                    'billing_starts' => 'admission_time',
                    'auto_apply_all_wards' => true,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => ['general', 'medical', 'icu', 'private'],
                'patient_category_rules' => [
                    'insurance' => ['discount_percentage' => 30],
                    'staff' => ['discount_percentage' => 70],
                ],
                'auto_trigger_conditions' => [
                    'trigger_on' => 'admission',
                    'auto_create_charges' => true,
                ],
                'payment_requirement' => 'deferred',
                'integration_points' => ['ward_management', 'nursing'],
            ],
            [
                'service_name' => 'Equipment Usage Fee',
                'service_code' => 'EQUIPMENT_USAGE',
                'description' => 'Medical equipment usage charges',
                'billing_type' => 'event_triggered',
                'base_amount' => 75.00,
                'percentage_rate' => null,
                'calculation_rules' => [
                    'charge_per_use' => true,
                    'equipment_tracking' => true,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => ['icu', 'surgical', 'emergency'],
                'patient_category_rules' => [
                    'insurance' => ['discount_percentage' => 15],
                ],
                'auto_trigger_conditions' => [
                    'trigger_on' => 'equipment_use',
                    'auto_create_charges' => false,
                ],
                'payment_requirement' => 'deferred',
                'integration_points' => ['equipment_management', 'nursing'],
            ],
        ];

        foreach ($templates as $template) {
            WardBillingTemplate::updateOrCreate(
                ['service_code' => $template['service_code']],
                $template
            );
        }
    }
}
