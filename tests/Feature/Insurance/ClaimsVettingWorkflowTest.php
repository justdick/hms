<?php

use App\Models\Diagnosis;
use App\Models\GdrgTariff;
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
    Permission::firstOrCreate(['name' => 'system.admin']);
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

// NHIS-specific vetting workflow tests

it('returns vetting data for a claim', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

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
        ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data");

    $response->assertOk()
        ->assertJsonStructure([
            'claim',
            'patient' => [
                'id',
                'name',
                'surname',
                'other_names',
                'date_of_birth',
                'gender',
                'folder_number',
            ],
            'attendance' => [
                'type_of_attendance',
                'date_of_attendance',
                'type_of_service',
            ],
            'diagnoses',
            'items' => [
                'investigations',
                'prescriptions',
                'procedures',
            ],
            'totals' => [
                'investigations',
                'prescriptions',
                'procedures',
                'gdrg',
                'grand_total',
            ],
            'is_nhis',
            'gdrg_tariffs',
            'can',
        ]);
});

it('requires G-DRG selection for NHIS claim approval', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    // Try to approve without G-DRG
    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'approve',
        ]);

    $response->assertSessionHasErrors('gdrg_tariff_id');

    // Claim should still be pending
    $claim->refresh();
    expect($claim->status)->toBe('pending_vetting');
});

it('approves NHIS claim with G-DRG selection', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    $gdrgTariff = GdrgTariff::factory()->create(['tariff_price' => 150.00]);

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'approve',
            'gdrg_tariff_id' => $gdrgTariff->id,
        ]);

    $response->assertRedirect();

    $claim->refresh();
    expect($claim->status)->toBe('vetted')
        ->and($claim->gdrg_tariff_id)->toBe($gdrgTariff->id)
        ->and((float) $claim->gdrg_amount)->toBe(150.00)
        ->and($claim->vetted_by)->toBe($user->id)
        ->and($claim->vetted_at)->not->toBeNull();
});

it('allows updating claim diagnoses', function () {
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

    $diagnoses = Diagnosis::factory()->count(2)->create();

    $response = $this->actingAs($user)
        ->postJson("/admin/insurance/claims/{$claim->id}/diagnoses", [
            'diagnoses' => [
                ['diagnosis_id' => $diagnoses[0]->id, 'is_primary' => true],
                ['diagnosis_id' => $diagnoses[1]->id, 'is_primary' => false],
            ],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Claim diagnoses updated successfully.',
        ]);

    expect($claim->claimDiagnoses()->count())->toBe(2)
        ->and($claim->claimDiagnoses()->where('is_primary', true)->count())->toBe(1);
});

it('returns G-DRG tariffs for NHIS claims in vetting data', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['insurance.vet-claims', 'insurance.view-claims']);

    $nhisProvider = InsuranceProvider::factory()->nhis()->create();
    $nhisPlan = InsurancePlan::factory()->create(['insurance_provider_id' => $nhisProvider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $nhisPlan->id,
    ]);

    // Create some G-DRG tariffs
    GdrgTariff::factory()->count(3)->create();

    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $patient->id,
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'pending_vetting',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/admin/insurance/claims/{$claim->id}/vetting-data");

    $response->assertOk()
        ->assertJson([
            'is_nhis' => true,
        ])
        ->assertJsonCount(3, 'gdrg_tariffs');
});

it('does not require G-DRG for non-NHIS claim approval', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.vet-claims');

    // Non-NHIS provider
    $provider = InsuranceProvider::factory()->create(['is_nhis' => false]);
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

    // Approve without G-DRG (should work for non-NHIS)
    $response = $this->actingAs($user)
        ->post("/admin/insurance/claims/{$claim->id}/vet", [
            'action' => 'approve',
        ]);

    $response->assertRedirect();

    $claim->refresh();
    expect($claim->status)->toBe('vetted')
        ->and($claim->gdrg_tariff_id)->toBeNull();
});
