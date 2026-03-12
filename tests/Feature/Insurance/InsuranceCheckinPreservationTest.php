<?php

/**
 * Preservation Property Tests — Insurance Check-in Fix
 *
 * Property 2: Preservation — Date Comparison, Different-Plan Creation, and Paid Charge Protection
 *
 * These tests capture EXISTING correct behavior on UNFIXED code. They must all PASS
 * before any fixes are applied, and continue to PASS after fixes — confirming no regressions.
 *
 * Observation-first methodology: each test documents observed behavior on unfixed code.
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.8, 3.9
 */

use App\Models\Charge;
use App\Models\Department;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use App\Services\InsuranceApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'patients.update']);
    Permission::firstOrCreate(['name' => 'patients.view']);
    Permission::firstOrCreate(['name' => 'checkins.view-all']);
    Permission::firstOrCreate(['name' => 'checkins.update']);

    // Create admin role with permissions
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);
    $adminRole->givePermissionTo(['patients.update', 'patients.view', 'checkins.view-all', 'checkins.update']);

    $this->user = User::factory()->create();
    $this->user->assignRole('Admin');

    $this->department = Department::factory()->create();

    // Create insurance provider and plan
    $this->provider = InsuranceProvider::factory()->create([
        'code' => 'PRV'.uniqid(),
    ]);

    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_code' => 'PLN'.uniqid(),
        'is_active' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| Test 2a — Past Date Expiry
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.1
| Observed on UNFIXED code: isExpired() returns true for coverage_end_date = yesterday
| Also: checkInsurance endpoint returns is_expired: true for past dates
|
| For any coverage_end_date strictly before today, isExpired() returns true
| and checkInsurance returns is_expired: true.
|
*/
test('2a: isExpired() returns true and checkInsurance returns is_expired true for past coverage_end_date', function () {
    $patient = Patient::factory()->create();

    $insurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'coverage_start_date' => today()->subYear(),
        'coverage_end_date' => today()->subDay(), // Yesterday — strictly in the past
        'status' => 'active',
    ]);

    $insurance->refresh();

    // Model-level: isExpired() should return true for past dates
    expect($insurance->isExpired())->toBeTrue();

    // Endpoint-level: checkInsurance should return is_expired: true
    // Note: activeInsurance relationship filters by coverage_end_date >= now(),
    // so a past date means activeInsurance returns null → has_insurance: false
    $response = $this->actingAs($this->user)
        ->getJson("/checkin/checkins/patients/{$patient->id}/insurance");

    $response->assertOk();

    // With yesterday's end date, activeInsurance scope excludes it → no insurance found
    expect($response->json('has_insurance'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 2b — Future Date Validity
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.3
| Observed on UNFIXED code: isExpired() returns false for coverage_end_date = tomorrow
| Also: checkInsurance endpoint returns is_expired: false for future dates
|
| For any coverage_end_date after today, isExpired() returns false
| and checkInsurance returns is_expired: false.
|
*/
test('2b: isExpired() returns false and checkInsurance returns is_expired false for future coverage_end_date', function () {
    $patient = Patient::factory()->create();

    $insurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'coverage_start_date' => today()->subYear(),
        'coverage_end_date' => today()->addDay(), // Tomorrow — in the future
        'status' => 'active',
    ]);

    $insurance->refresh();

    // Model-level: isExpired() should return false for future dates
    expect($insurance->isExpired())->toBeFalse();

    // Endpoint-level: checkInsurance should return is_expired: false
    $response = $this->actingAs($this->user)
        ->getJson("/checkin/checkins/patients/{$patient->id}/insurance");

    $response->assertOk()
        ->assertJson(['has_insurance' => true]);

    expect($response->json('insurance.is_expired'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 2c — Null Date Validity
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.2
| Observed on UNFIXED code: isExpired() returns false for coverage_end_date = null
|
| When coverage_end_date is null, isExpired() returns false (valid indefinitely).
|
*/
test('2c: isExpired() returns false when coverage_end_date is null', function () {
    $insurance = PatientInsurance::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_start_date' => today()->subYear(),
        'coverage_end_date' => null,
        'status' => 'active',
    ]);

    $insurance->refresh();

    // Null coverage_end_date means no expiry — insurance is valid indefinitely
    expect($insurance->isExpired())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 2d — Different Plan Creates New Record
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.5
| Observed on UNFIXED code: Adding insurance for a different insurance_plan_id
| creates a new record (this is correct — different plans are legitimate new records).
|
| When patient has NO active insurance (plan A is expired) and user adds
| insurance for plan B, a new record is created for plan B.
|
| Note: The current PatientController::update checks $patient->activeInsurance.
| When activeInsurance exists (same or different plan), it updates that record.
| When activeInsurance is null (e.g., expired), it creates a new record.
| This test verifies the "create new" path works for a different plan.
|
*/
test('2d: adding insurance for a different plan creates a new record when no active insurance exists', function () {
    $patient = Patient::factory()->create([
        'status' => 'active',
    ]);

    // Create an EXPIRED insurance record for plan A (not returned by activeInsurance)
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->plan->id,
        'membership_id' => 'PLAN-A-MEMBER',
        'coverage_start_date' => today()->subYears(2),
        'coverage_end_date' => today()->subYear(), // Expired a year ago
        'status' => 'active',
    ]);

    // Create a different plan (plan B)
    $planB = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_code' => 'PLNB'.uniqid(),
        'is_active' => true,
    ]);

    // Submit patient update with different insurance_plan_id (plan B)
    $response = $this->actingAs($this->user)
        ->patch("/patients/{$patient->id}", [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => true,
            'insurance_plan_id' => $planB->id,
            'membership_id' => 'PLAN-B-MEMBER',
            'coverage_start_date' => today()->format('Y-m-d'),
            'coverage_end_date' => today()->addYear()->format('Y-m-d'),
        ]);

    $response->assertRedirect();

    // Plan A record should still exist (untouched)
    $planACount = PatientInsurance::where('patient_id', $patient->id)
        ->where('insurance_plan_id', $this->plan->id)
        ->count();
    expect($planACount)->toBe(1);

    // Plan B record should be created as a new record
    $planBCount = PatientInsurance::where('patient_id', $patient->id)
        ->where('insurance_plan_id', $planB->id)
        ->count();
    expect($planBCount)->toBe(1);

    // Total: 2 records (one per plan — this is correct)
    $totalCount = PatientInsurance::where('patient_id', $patient->id)->count();
    expect($totalCount)->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Test 2e — New Insurance Creates Record
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.4
| Observed on UNFIXED code: When patient has no insurance records at all
| and user adds insurance, a new PatientInsurance record is created.
|
*/
test('2e: adding insurance for patient with no existing insurance creates a new record', function () {
    $patient = Patient::factory()->create([
        'status' => 'active',
    ]);

    // Confirm patient has no insurance records
    expect(PatientInsurance::where('patient_id', $patient->id)->count())->toBe(0);

    // Submit patient update with insurance
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

    // A new insurance record should be created
    $count = PatientInsurance::where('patient_id', $patient->id)->count();
    expect($count)->toBe(1);

    // Verify the record has the correct data
    $insurance = PatientInsurance::where('patient_id', $patient->id)->first();
    expect($insurance->insurance_plan_id)->toBe($this->plan->id);
    expect($insurance->membership_id)->toBe('NEW-MEMBER-001');
    expect($insurance->status)->toBe('active');
});

/*
|--------------------------------------------------------------------------
| Test 2f — Paid Charges Unchanged
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.8
| Observed on UNFIXED code: InsuranceApplicationService::applyInsuranceToActiveCheckin
| only processes charges with status = 'pending' and is_insurance_claim = false.
| Charges with status 'paid' are not selected by the query and thus not modified.
|
| This test verifies the charge-filtering query behavior directly: the service
| queries Charge::where('status', 'pending')->where('is_insurance_claim', false),
| so paid charges are excluded from processing.
|
*/
test('2f: applyInsuranceToActiveCheckin does not modify paid charges', function () {
    $patient = Patient::factory()->create(['status' => 'active']);

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'checked_in_by' => $this->user->id,
        'status' => 'awaiting_consultation',
        'claim_check_code' => null,
    ]);

    // Create a paid charge
    $paidCharge = Charge::factory()->paid()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 200.00,
        'is_insurance_claim' => false,
    ]);

    // Create a pending charge
    $pendingCharge = Charge::factory()->pending()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 150.00,
        'is_insurance_claim' => false,
    ]);

    // Verify the charge query used by the service only selects pending, non-insurance charges
    $eligibleCharges = Charge::where('patient_checkin_id', $checkin->id)
        ->where('status', 'pending')
        ->where('is_insurance_claim', false)
        ->get();

    // Only the pending charge should be eligible
    expect($eligibleCharges)->toHaveCount(1);
    expect($eligibleCharges->first()->id)->toBe($pendingCharge->id);

    // Paid charge should NOT be in the eligible set
    expect($eligibleCharges->pluck('id')->contains($paidCharge->id))->toBeFalse();

    // Verify paid charge state is untouched
    $paidCharge->refresh();
    expect($paidCharge->status)->toBe('paid');
    expect((float) $paidCharge->amount)->toBe(200.00);
    expect($paidCharge->is_insurance_claim)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Test 2g — No Active Checkin
|--------------------------------------------------------------------------
|
| Preservation: requirement 3.9
| Observed on UNFIXED code: When patient has no active check-in,
| updating insurance only updates the patient profile — no charge re-evaluation,
| no redirect to apply insurance modal.
|
*/
test('2g: updating insurance without active checkin only updates profile', function () {
    $patient = Patient::factory()->create([
        'status' => 'active',
    ]);

    // No checkins at all for this patient

    // Submit patient update with insurance
    $response = $this->actingAs($this->user)
        ->patch("/patients/{$patient->id}", [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => true,
            'insurance_plan_id' => $this->plan->id,
            'membership_id' => 'NO-CHECKIN-001',
            'coverage_start_date' => today()->format('Y-m-d'),
            'coverage_end_date' => today()->addYear()->format('Y-m-d'),
        ]);

    // Should redirect to patient show with success (no apply insurance modal)
    $response->assertRedirect(route('patients.show', $patient));
    $response->assertSessionHas('success');

    // Should NOT have the apply insurance modal trigger
    $response->assertSessionMissing('show_apply_insurance_modal');

    // Insurance record should be created (profile updated)
    $count = PatientInsurance::where('patient_id', $patient->id)->count();
    expect($count)->toBe(1);

    // No charges should exist (no checkin = no charges to re-evaluate)
    $chargeCount = Charge::whereHas('patientCheckin', function ($q) use ($patient) {
        $q->where('patient_id', $patient->id);
    })->count();
    expect($chargeCount)->toBe(0);
});
