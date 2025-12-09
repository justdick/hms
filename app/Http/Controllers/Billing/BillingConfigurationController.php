<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingConfiguration;
use App\Models\Department;
use App\Models\DepartmentBilling;
use App\Models\ServiceChargeRule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingConfigurationController extends Controller
{
    public function index(): Response
    {
        $systemConfig = BillingConfiguration::active()->get()->groupBy('category');

        // Get all departments with their billing configuration (if exists)
        $departments = Department::active()
            ->with('billing')
            ->orderBy('name')
            ->get()
            ->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'type' => $department->type,
                    'billing' => $department->billing,
                ];
            });

        $serviceRules = ServiceChargeRule::active()
            ->get()
            ->groupBy('service_type');

        return Inertia::render('Billing/Configuration/Index', [
            'systemConfig' => $systemConfig,
            'departments' => $departments,
            'serviceRules' => $serviceRules,
        ]);
    }

    public function updateSystemConfig(Request $request)
    {
        $request->validate([
            'configs' => 'required|array',
            'configs.*.key' => 'required|string',
            'configs.*.value' => 'required',
            'configs.*.category' => 'required|string',
            'configs.*.description' => 'nullable|string',
        ]);

        foreach ($request->configs as $config) {
            BillingConfiguration::setValue(
                $config['key'],
                $config['value'],
                $config['category'],
                $config['description'] ?? null
            );
        }

        return redirect()->back()->with('success', 'System configuration updated successfully');
    }

    public function updateDepartmentBilling(Request $request, DepartmentBilling $departmentBilling)
    {
        $request->validate([
            'consultation_fee' => 'required|numeric|min:0',
            'equipment_fee' => 'nullable|numeric|min:0',
            'emergency_surcharge' => 'nullable|numeric|min:0',
            'payment_required_before_consultation' => 'boolean',
            'emergency_override_allowed' => 'boolean',
            'payment_grace_period_minutes' => 'nullable|integer|min:0',
            'allow_partial_payment' => 'boolean',
            'payment_plan_available' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $departmentBilling->update([
            'consultation_fee' => $request->consultation_fee,
            'equipment_fee' => $request->equipment_fee ?? 0,
            'emergency_surcharge' => $request->emergency_surcharge ?? 0,
            'payment_required_before_consultation' => $request->boolean('payment_required_before_consultation'),
            'emergency_override_allowed' => $request->boolean('emergency_override_allowed'),
            'payment_grace_period_minutes' => $request->payment_grace_period_minutes ?? 30,
            'allow_partial_payment' => $request->boolean('allow_partial_payment'),
            'payment_plan_available' => $request->boolean('payment_plan_available'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Department billing configuration updated successfully');
    }

    public function updateServiceRule(Request $request, ServiceChargeRule $serviceRule)
    {
        $request->validate([
            'charge_timing' => 'required|in:on_checkin,before_service,after_service',
            'payment_required' => 'required|in:mandatory,optional,deferred',
            'payment_timing' => 'required|in:immediate,before_service,after_service',
            'emergency_override_allowed' => 'boolean',
            'partial_payment_allowed' => 'boolean',
            'payment_plans_available' => 'boolean',
            'grace_period_days' => 'nullable|integer|min:0',
            'service_blocking_enabled' => 'boolean',
            'hide_details_until_paid' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $serviceRule->update([
            'charge_timing' => $request->charge_timing,
            'payment_required' => $request->payment_required,
            'payment_timing' => $request->payment_timing,
            'emergency_override_allowed' => $request->boolean('emergency_override_allowed'),
            'partial_payment_allowed' => $request->boolean('partial_payment_allowed'),
            'payment_plans_available' => $request->boolean('payment_plans_available'),
            'grace_period_days' => $request->grace_period_days ?? 0,
            'service_blocking_enabled' => $request->boolean('service_blocking_enabled'),
            'hide_details_until_paid' => $request->boolean('hide_details_until_paid'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->back()->with('success', 'Service rule updated successfully');
    }

    public function createDepartmentBilling(Request $request)
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id|unique:department_billings,department_id',
            'consultation_fee' => 'required|numeric|min:0',
            'equipment_fee' => 'nullable|numeric|min:0',
            'emergency_surcharge' => 'nullable|numeric|min:0',
            'payment_required_before_consultation' => 'boolean',
            'emergency_override_allowed' => 'boolean',
            'payment_grace_period_minutes' => 'nullable|integer|min:0',
            'allow_partial_payment' => 'boolean',
            'payment_plan_available' => 'boolean',
        ]);

        $department = Department::findOrFail($request->department_id);

        DepartmentBilling::create([
            'department_id' => $department->id,
            'department_code' => $department->code,
            'department_name' => $department->name,
            'consultation_fee' => $request->consultation_fee,
            'equipment_fee' => $request->equipment_fee ?? 0,
            'emergency_surcharge' => $request->emergency_surcharge ?? 0,
            'payment_required_before_consultation' => $request->boolean('payment_required_before_consultation'),
            'emergency_override_allowed' => $request->boolean('emergency_override_allowed'),
            'payment_grace_period_minutes' => $request->payment_grace_period_minutes ?? 30,
            'allow_partial_payment' => $request->boolean('allow_partial_payment'),
            'payment_plan_available' => $request->boolean('payment_plan_available'),
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Department billing configuration created successfully');
    }

    public function createServiceRule(Request $request)
    {
        $request->validate([
            'service_type' => 'required|string',
            'service_code' => 'nullable|string',
            'service_name' => 'required|string',
            'charge_timing' => 'required|in:on_checkin,before_service,after_service',
            'payment_required' => 'required|in:mandatory,optional,deferred',
            'payment_timing' => 'required|in:immediate,before_service,after_service',
            'emergency_override_allowed' => 'boolean',
            'partial_payment_allowed' => 'boolean',
            'payment_plans_available' => 'boolean',
            'grace_period_days' => 'nullable|integer|min:0',
            'service_blocking_enabled' => 'boolean',
            'hide_details_until_paid' => 'boolean',
        ]);

        ServiceChargeRule::create(array_merge($request->all(), ['is_active' => true]));

        return redirect()->back()->with('success', 'Service rule created successfully');
    }

    public function bulkCreateDepartmentBilling(Request $request)
    {
        $request->validate([
            'department_ids' => 'required|array|min:1',
            'department_ids.*' => 'exists:departments,id',
            'consultation_fee' => 'required|numeric|min:0',
            'equipment_fee' => 'nullable|numeric|min:0',
            'emergency_surcharge' => 'nullable|numeric|min:0',
            'payment_required_before_consultation' => 'boolean',
            'emergency_override_allowed' => 'boolean',
            'payment_grace_period_minutes' => 'nullable|integer|min:0',
        ]);

        $departments = Department::whereIn('id', $request->department_ids)->get();
        $created = 0;
        $updated = 0;

        foreach ($departments as $department) {
            $billing = DepartmentBilling::updateOrCreate(
                ['department_id' => $department->id],
                [
                    'department_code' => $department->code,
                    'department_name' => $department->name,
                    'consultation_fee' => $request->consultation_fee,
                    'equipment_fee' => $request->equipment_fee ?? 0,
                    'emergency_surcharge' => $request->emergency_surcharge ?? 0,
                    'payment_required_before_consultation' => $request->boolean('payment_required_before_consultation'),
                    'emergency_override_allowed' => $request->boolean('emergency_override_allowed'),
                    'payment_grace_period_minutes' => $request->payment_grace_period_minutes ?? 30,
                    'is_active' => true,
                ]
            );

            if ($billing->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }
        }

        $message = "Billing configured for {$departments->count()} departments";
        if ($created > 0 && $updated > 0) {
            $message .= " ({$created} created, {$updated} updated)";
        }

        return redirect()->back()->with('success', $message);
    }
}
