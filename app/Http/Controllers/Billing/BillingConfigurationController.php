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

        $departmentBilling = DepartmentBilling::active()->get();

        $serviceRules = ServiceChargeRule::active()
            ->get()
            ->groupBy('service_type');

        $departments = Department::active()->get(['id', 'name', 'code']);

        return Inertia::render('Billing/Configuration/Index', [
            'systemConfig' => $systemConfig,
            'departmentBilling' => $departmentBilling,
            'serviceRules' => $serviceRules,
            'departments' => $departments,
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
        ]);

        $departmentBilling->update($request->all());

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
        ]);

        $serviceRule->update($request->all());

        return redirect()->back()->with('success', 'Service rule updated successfully');
    }

    public function createDepartmentBilling(Request $request)
    {
        $request->validate([
            'department_code' => 'required|string|unique:department_billings,department_code',
            'department_name' => 'required|string',
            'consultation_fee' => 'required|numeric|min:0',
            'equipment_fee' => 'nullable|numeric|min:0',
            'emergency_surcharge' => 'nullable|numeric|min:0',
            'payment_required_before_consultation' => 'boolean',
            'emergency_override_allowed' => 'boolean',
            'payment_grace_period_minutes' => 'nullable|integer|min:0',
            'allow_partial_payment' => 'boolean',
            'payment_plan_available' => 'boolean',
        ]);

        DepartmentBilling::create(array_merge($request->all(), ['is_active' => true]));

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
}
