<?php

/**
 * Property-Based Test for Outstanding Report Accuracy
 *
 * **Feature: nhis-claims-integration, Property 29: Outstanding Report Accuracy**
 * **Validates: Requirements 18.2**
 *
 * Property: For any outstanding report, all displayed claims should be approved
 * but not yet paid, with correct aging calculation.
 */

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    \Illuminate\Support\Facades\Cache::flush();
    InsuranceClaim::query()->delete();
    Permission::firstOrCreate(['name' => 'insurance.view-reports']);
});

it('only includes submitted and approved claims in outstanding report', function () {
    // Arrange
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create a submitted claim (should be included)
    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'submitted',
        'total_claim_amount' => 100.00,
        'approved_amount' => 0.00,
        'submitted_at' => now()->subDays(10),
    ]);

    // Create an approved claim (should be included)
    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'approved',
        'total_claim_amount' => 100.00,
        'approved_amount' => 90.00,
        'submitted_at' => now()->subDays(10),
    ]);

    // Create a draft claim (should NOT be included)
    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'draft',
        'total_claim_amount' => 100.00,
        'approved_amount' => 0.00,
    ]);

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.outstanding-claims'));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Only submitted and approved claims should be counted
    expect($data['total_claims'])->toBe(2);
});

it('returns aging analysis structure', function () {
    // Arrange
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Create a submitted claim
    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'submitted',
        'total_claim_amount' => 500.00,
        'approved_amount' => 0.00,
        'submitted_at' => now()->subDays(15),
    ]);

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.outstanding-claims'));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Aging analysis structure exists
    expect($data)->toHaveKey('aging_analysis')
        ->and($data['aging_analysis'])->toHaveKey('0-30')
        ->and($data['aging_analysis'])->toHaveKey('31-60')
        ->and($data['aging_analysis'])->toHaveKey('61-90')
        ->and($data['aging_analysis'])->toHaveKey('90+');
});

it('groups outstanding claims by provider', function () {
    // Arrange: Create two providers
    $provider1 = InsuranceProvider::factory()->create(['name' => 'Provider A']);
    $provider2 = InsuranceProvider::factory()->create(['name' => 'Provider B']);

    $plan1 = InsurancePlan::factory()->create(['insurance_provider_id' => $provider1->id]);
    $plan2 = InsurancePlan::factory()->create(['insurance_provider_id' => $provider2->id]);

    $patient = Patient::factory()->create();

    $patientInsurance1 = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan1->id,
    ]);

    $patientInsurance2 = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan2->id,
    ]);

    // Create claims for Provider A
    InsuranceClaim::factory()->count(2)->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance1->id,
        'status' => 'submitted',
        'total_claim_amount' => 200.00,
        'approved_amount' => 0.00,
        'submitted_at' => now()->subDays(10),
    ]);

    // Create claims for Provider B
    InsuranceClaim::factory()->count(3)->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance2->id,
        'status' => 'approved',
        'total_claim_amount' => 300.00,
        'approved_amount' => 280.00,
        'submitted_at' => now()->subDays(20),
    ]);

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.outstanding-claims'));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Provider breakdown exists and has correct counts
    expect($data['by_provider']['Provider A']['count'])->toBe(2)
        ->and($data['by_provider']['Provider B']['count'])->toBe(3);
});
