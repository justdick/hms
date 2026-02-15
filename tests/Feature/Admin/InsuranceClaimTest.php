<?php

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Create permissions if they don't exist
    Permission::firstOrCreate(['name' => 'system.admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'insurance.view-claims', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['system.admin', 'insurance.view-claims']);
    actingAs($this->user);
});

it('displays insurance claims index page', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claims = InsuranceClaim::factory()->count(3)->create([
        'patient_insurance_id' => $patientInsurance->id,
    ]);

    $response = $this->get(route('admin.insurance.claims.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 3)
        ->has('providers.data')
        ->has('stats')
    );
});

it('filters claims by status', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'draft',
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'vetted',
    ]);

    $response = $this->get(route('admin.insurance.claims.index', ['status' => 'draft']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 1)
        ->where('claims.data.0.status', 'draft')
    );
});

it('filters claims by insurance provider', function () {
    $provider1 = InsuranceProvider::factory()->create();
    $provider2 = InsuranceProvider::factory()->create();

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

    InsuranceClaim::factory()->count(2)->create([
        'patient_insurance_id' => $patientInsurance1->id,
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance2->id,
    ]);

    $response = $this->get(route('admin.insurance.claims.index', ['provider_id' => $provider1->id]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 2)
    );
});

it('searches claims by claim code', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'claim_check_code' => 'CLM-123456',
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'claim_check_code' => 'CLM-789012',
    ]);

    $response = $this->get(route('admin.insurance.claims.index', ['search' => 'CLM-123456']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 1)
        ->where('claims.data.0.claim_check_code', 'CLM-123456')
    );
});


it('searches claims by folder_id using exact match', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $exactMatch = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'folder_id' => '32/2025',
        'status' => 'pending_vetting',
    ]);

    $partialMatch = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'folder_id' => '3132/2025',
        'status' => 'pending_vetting',
    ]);

    $response = $this->get(route('admin.insurance.claims.index', ['search' => '32/2025', 'status' => 'all']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 1)
        ->where('claims.data.0.folder_id', '32/2025')
    );
});

it('searches claims by membership_id using exact match', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $exactMatch = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'membership_id' => '12345678',
        'status' => 'pending_vetting',
    ]);

    $partialMatch = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'membership_id' => '912345678',
        'status' => 'pending_vetting',
    ]);

    $response = $this->get(route('admin.insurance.claims.index', ['search' => '12345678', 'status' => 'all']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 1)
        ->where('claims.data.0.membership_id', '12345678')
    );
});

it('searches claims by patient name using partial match', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'patient_surname' => 'AGYEIWAA',
        'status' => 'pending_vetting',
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'patient_surname' => 'MENSAH',
        'status' => 'pending_vetting',
    ]);

    $response = $this->get(route('admin.insurance.claims.index', ['search' => 'AGYE', 'status' => 'all']));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
        ->has('claims.data', 1)
        ->where('claims.data.0.patient.surname', 'AGYEIWAA')
    );
});


it('redirects back to the same filtered page after deleting a claim', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    $claim = InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'vetted',
    ]);

    $response = $this->from(route('admin.insurance.claims.index', ['status' => 'vetted']))
        ->delete(route('admin.insurance.claims.destroy', $claim));

    $response->assertRedirect(route('admin.insurance.claims.index', ['status' => 'vetted']));
});
