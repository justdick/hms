<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentBilling;
use Illuminate\Database\Seeder;

class MinorProceduresDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Minor Procedures department
        $department = Department::updateOrCreate(
            ['code' => 'MINPROC'],
            [
                'name' => 'Minor Procedures',
                'code' => 'MINPROC',
                'description' => 'Minor Procedures Department for nursing procedures',
                'type' => 'opd',
                'is_active' => true,
            ]
        );

        // Create department billing configuration
        DepartmentBilling::updateOrCreate(
            ['department_id' => $department->id],
            [
                'department_code' => 'MINPROC',
                'department_name' => 'Minor Procedures',
                'consultation_fee' => 30.00, // Lower fee for minor procedures
                'equipment_fee' => 5.00,
                'emergency_surcharge' => 15.00,
                'payment_required_before_consultation' => false, // Vitals not required
                'emergency_override_allowed' => true,
                'payment_grace_period_minutes' => 30,
                'allow_partial_payment' => true,
                'payment_plan_available' => true,
            ]
        );
    }
}
