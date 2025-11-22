<?php

use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'insurance.vet-claims']);
    Permission::firstOrCreate(['name' => 'insurance.view-claims']);
});

it('allows authorized user to approve a claim', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'total_claim_amount' => 500.00,
        'insurance_covered_amount' => 400.00,
        'patient_copay_amount' => 100.00,
    ]);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'insurance_pays' => 400.00,
    ]);

    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'approve',
        ]);

    $response->assertRedirect();

    $claim->refresh();
    expect($claim->status)->toBe('vetted')
        ->and($claim->vetted_by)->toBe($user->id)
        ->and($claim->vetted_at)->not->toBeNull();
});

it('requires rejection reason when rejecting a claim', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'reject',
        ]);

    $response->assertSessionHasErrors('rejection_reason');
});

it('allows authorized user to reject a claim with reason', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'reject',
            'rejection_reason' => 'Incomplete documentation',
        ]);

    $response->assertRedirect();

    $claim->refresh();
    expect($claim->status)->toBe('rejected')
        ->and($claim->rejection_reason)->toBe('Incomplete documentation')
        ->and($claim->vetted_by)->toBe($user->id)
        ->and($claim->vetted_at)->not->toBeNull();
});

it('returns JSON response when requested via API', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson("/admin/insurance/claims/{$claim->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'claim' => [
                'id',
                'claim_check_code',
                'patient_full_name',
                'status',
                'total_claim_amount',
                'items',
            ],
            'can' => [
                'vet',
                'submit',
                'approve',
                'reject',
            ],
        ]);
});

it('prevents unauthorized user from vetting claims', function () {
    $user = User::factory()->create();

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'approve',
        ]);

    $response->assertForbidden();
});

it('displays claims list with pending vetting status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
        'patient_surname' => 'Doe',
        'patient_other_names' => 'John',
    ]);

    $response = $this->actingAs($user)
        ->get('/admin/insurance/claims');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Insurance/Claims/Index')
            ->has('claims.data', 1)
            ->where('claims.data.0.status', 'pending_vetting')
        );
});

it('filters claims by status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'vetted',
    ]);

    $response = $this->actingAs($user)
        ->get('/admin/insurance/claims?status=pending_vetting');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Insurance/Claims/Index')
            ->has('claims.data', 1)
            ->where('claims.data.0.status', 'pending_vetting')
        );
});

it('searches claims by claim code', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-claims');

    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'claim_check_code' => 'CC-20250115-0001',
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'claim_check_code' => 'CC-20250115-0002',
    ]);

    $response = $this->actingAs($user)
        ->get('/admin/insurance/claims?search=0001');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Insurance/Claims/Index')
            ->has('claims.data', 1)
            ->where('claims.data.0.claim_check_code', 'CC-20250115-0001')
        );
});
