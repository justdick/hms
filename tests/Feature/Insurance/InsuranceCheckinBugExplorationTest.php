<?php

/**
 * Bug Condition Exploration Tests — Insurance Check-in Fix
 *
 * Property 1: Bug Condition — Insurance Expiry Day Exclusion, Duplicate Records, and Missing Charge Summary
 *
 * These tests encode the EXPECTED (correct) behavior. They are designed to FAIL on unfixed code,
 * confirming the bugs exist. After the fixes are applied, these same tests will PASS,
 * confirming the bugs are resolved.
 *
 * DO NOT fix the code or the tests when they fail — failure IS the expected outcome on unfixed code.
 *
 * Requirements: 1.1, 1.3, 1.5
 */

use App\Models\Charge;
use App\Models\Department;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'patients.update']);
    Permission::firstOrCreate(['name' => 'patients.view']);
    Permission::firstOrCreate(['name' => 'checkins.view-all']);

    // Create admin role with permissions
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $adminRole->givePermissionTo(['patients.update', 'patients.view', 'checkins.view-all']);

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    $this->department = Department::factory()->create();

    // Create insurance provider and plan
    $this->provider = InsuranceProvider::factory()->create([
        'code' => 'BUG'.uniqid(),
    ]);

    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_code' => 'BUGP'.uniqid(),
        'is_active' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 1a — Expiry Day: PatientInsurance::isExpired() on coverage_end_date == today
|--------------------------------------------------------------------------
|
| Bug Condition: coverage_end_date == TODAY AND isPast(coverage_end_date) == true
| Expected Behavior: isExpired() returns false (insurance valid for entire expiry day)
| On UNFIXED code: isExpired() returns true → test FAILS (confirms bug exists)
|
*/
test('1a: PatientInsurance::isExpired() returns false when coverage_end_date is today', function () {
    $insurance = PatientInsurance::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_start_date' => today()->subYear(),
        'coverage_end_date' => today(),
        'status' => 'active',
    ]);

    // Refresh to ensure casts are applied
    $insurance->refresh();

    // Expected: insurance expiring today should NOT be considered expired
    expect($insurance->isExpired())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 1b — CheckInsurance Endpoint: is_expired flag on coverage_end_date == today
|--------------------------------------------------------------------------
|
| Bug Condition: controller uses isPast() on expiry day → returns is_expired: true
| Expected Behavior: checkInsurance returns is_expired: false when coverage_end_date == today
| On UNFIXED code: is_expired is true → test FAILS (confirms bug exists)
|
*/
test('1b: checkInsurance endpoint returns is_expired false when coverage_end_date is today', function () {
    $patient = Patient::factory()->create();

    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'coverage_start_date' => today()->subYear(),
        'coverage_end_date' => today(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/checkin/checkins/patients/{$patient->id}/insurance");

    $response->assertOk()
        ->assertJson([
            'has_insurance' => true,
        ]);

    // Expected: is_expired should be false on the expiry day
    // The is_expired flag is nested inside the insurance object
    expect($response->json('insurance.is_expired'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 1c — Duplicate Insurance: same plan creates duplicate instead of updating
|--------------------------------------------------------------------------
|
| Bug Condition: activeInsurance returns null for expired same-plan record,
|                so PatientController::update creates a duplicate
| Expected Behavior: exactly 1 record per plan after update (upsert)
| On UNFIXED code: 2 records created → test FAILS (confirms bug exists)
|
*/
test('1c: updating patient insurance for same plan updates existing record instead of creating duplicate', function () {
    $patient = Patient::factory()->create([
        'status' => 'active',
    ]);

    // Create an expired insurance record for the same plan
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'membership_id' => 'OLD-MEMBER-001',
        'coverage_start_date' => today()->subYear(),
        'coverage_end_date' => today()->subMonth(), // Expired last month
        'status' => 'active',
    ]);

    // Submit patient update with same insurance_plan_id but new dates
    $response = $this->actingAs($this->user)
        ->patch("/patients/{$patient->id}", [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => true,
            'insurance_plan_id' => $this->plan->id,
            'membership_id' => 'NEW-MEMBER-001',
            'coverage_start_date' => today()->format('Y-m-d'),
            'coverage_end_date' => today()->addYear()->format('Y-m-d'),
        ]);

    $response->assertRedirect();

    // Expected: exactly 1 insurance record for this plan (updated, not duplicated)
    $recordCount = PatientInsurance::where('patient_id', $patient->id)
        ->where('insurance_plan_id', $this->plan->id)
        ->count();

    expect($recordCount)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Test 1d — Charge Summary: getActiveCheckinWithoutInsurance missing charge data
|--------------------------------------------------------------------------
|
| Bug Condition: endpoint does not return pending_charges_count or pending_charges_total
| Expected Behavior: response includes pending charge count and total
| On UNFIXED code: keys missing from response → test FAILS (confirms bug exists)
|
| getActiveCheckinWithoutInsurance is exposed as the Inertia prop
| `active_checkin_without_insurance` on the Patient Show page.
| We create a patient with an active checkin (no insurance) and pending charges,
| then request the show page and verify the prop includes charge summary data.
|
*/
test('1d: active_checkin_without_insurance includes pending charge count and total', function () {
    $patient = Patient::factory()->create([
        'status' => 'active',
    ]);

    // Create an active checkin without insurance (no claim_check_code)
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => null,
    ]);

    // Create pending charges on this checkin
    Charge::factory()->pending()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 150.00,
        'is_insurance_claim' => false,
    ]);
    Charge::factory()->pending()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 250.00,
        'is_insurance_claim' => false,
    ]);
    Charge::factory()->pending()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 280.00,
        'is_insurance_claim' => false,
    ]);

    // Request the patient show page — this calls getActiveCheckinWithoutInsurance internally
    // and passes the result as the `active_checkin_without_insurance` Inertia prop
    $response = $this->actingAs($this->user)
        ->get("/patients/{$patient->id}");

    $response->assertOk();

    // Extract the Inertia prop
    $activeCheckin = $response->viewData('page')['props']['active_checkin_without_insurance'];

    // The checkin should be found (patient has active checkin without insurance)
    expect($activeCheckin)->not->toBeNull();
    expect($activeCheckin['id'])->toBe($checkin->id);

    // Expected: response should include pending charge summary
    // Bug: these keys do not exist in the current response
    expect($activeCheckin)->toHaveKey('pending_charges_count');
    expect($activeCheckin)->toHaveKey('pending_charges_total');
    expect($activeCheckin['pending_charges_count'])->toBe(3);
    expect((float) $activeCheckin['pending_charges_total'])->toBe(680.00);
});
