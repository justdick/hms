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
        // Only truly global settings - service-specific settings are in ServiceChargeRules
        $configurations = [
            [
                'key' => 'auto_billing_enabled',
                'category' => 'general',
                'value' => true,
                'description' => 'Master switch: Enable automatic billing when services are rendered',
            ],
            [
                'key' => 'emergency_override_global',
                'category' => 'general',
                'value' => true,
                'description' => 'Allow emergency override for all billing requirements system-wide',
            ],
            [
                'key' => 'default_payment_grace_period',
                'category' => 'general',
                'value' => 30,
                'description' => 'Default grace period for payments in minutes',
            ],
            [
                'key' => 'currency',
                'category' => 'display',
                'value' => 'GHS',
                'description' => 'System currency code (ISO 4217)',
            ],
            [
                'key' => 'currency_symbol',
                'category' => 'display',
                'value' => '₵',
                'description' => 'Currency symbol for display',
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
                'service_code' => null,
                'service_name' => 'Ward Services',
                'charge_timing' => 'before_service',
                'payment_required' => 'mandatory',
                'payment_timing' => 'immediate',
                'emergency_override_allowed' => true,
                'partial_payment_allowed' => true,
                'payment_plans_available' => true,
                'grace_period_days' => 1,
                'late_fees_enabled' => false,
                'service_blocking_enabled' => false,
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
            [
                'service_type' => 'radiology',
                'service_code' => null,
                'service_name' => 'Radiology/Imaging',
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
                'service_name' => 'Daily Ward Fee',
                'service_code' => 'DAILY_WARD_FEE',
                'description' => 'Standard daily ward accommodation fee',
                'billing_type' => 'daily',
                'base_amount' => 100.00,
                'nhis_amount' => 0.00, // NHIS patients don't pay daily admission fees
                'percentage_rate' => null,
                'calculation_rules' => [
                    'billing_starts' => 'midnight',
                    'charge_on_admission_day' => true,
                    'charge_on_discharge_day' => false,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => null,
                'patient_category_rules' => null,
                'auto_trigger_conditions' => [
                    'trigger_on' => 'daily_schedule',
                    'auto_create_charges' => true,
                ],
                'payment_requirement' => 'deferred',
                'integration_points' => ['ward_management', 'admission'],
            ],
            [
                'service_name' => 'Daily Nursing Care',
                'service_code' => 'DAILY_NURSING',
                'description' => 'Daily nursing care and monitoring fee',
                'billing_type' => 'daily',
                'base_amount' => 50.00,
                'nhis_amount' => 0.00, // NHIS patients don't pay daily nursing fees
                'percentage_rate' => null,
                'calculation_rules' => [
                    'billing_starts' => 'midnight',
                    'charge_on_admission_day' => true,
                    'charge_on_discharge_day' => false,
                ],
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'applicable_ward_types' => null,
                'patient_category_rules' => null,
                'auto_trigger_conditions' => [
                    'trigger_on' => 'daily_schedule',
                    'auto_create_charges' => true,
                ],
                'payment_requirement' => 'deferred',
                'integration_points' => ['ward_management', 'nursing'],
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
