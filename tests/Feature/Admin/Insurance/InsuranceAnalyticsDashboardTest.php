<?php

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    \Illuminate\Support\Facades\Cache::flush();

    // Create the permission if it doesn't exist
    Permission::firstOrCreate(['name' => 'system.admin', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('system.admin');
});

it('renders analytics dashboard page', function () {
    $response = $this->actingAs($this->admin)
        ->get('/admin/insurance/reports');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/Index')
    );
});

it('returns claims summary data as JSON', function () {
    $provider = InsuranceProvider::factory()->create(['name' => 'Test Provider']);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->count(3)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'submitted',
        'total_claim_amount' => 1000,
        'approved_amount' => 900,
        'payment_amount' => 0,
        'date_of_attendance' => now(),
    ]);

    InsuranceClaim::factory()->count(2)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'paid',
        'total_claim_amount' => 500,
        'approved_amount' => 450,
        'payment_amount' => 450,
        'date_of_attendance' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/admin/insurance/reports/claims-summary', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'total_claims',
            'total_claimed_amount',
            'total_approved_amount',
            'total_paid_amount',
            'outstanding_amount',
            'status_breakdown',
            'claims_by_provider',
        ],
    ]);

    expect($response->json('data.total_claims'))->toBe(5);
    expect($response->json('data.total_claimed_amount'))->toBe(4000);
});

it('returns revenue analysis data as JSON', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->count(2)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'paid',
        'total_claim_amount' => 1000,
        'approved_amount' => 900,
        'payment_amount' => 900,
        'date_of_attendance' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/admin/insurance/reports/revenue-analysis', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'insurance_revenue',
            'cash_revenue',
            'total_revenue',
            'insurance_percentage',
            'cash_percentage',
            'monthly_trend',
        ],
    ]);

    expect($response->json('data.insurance_revenue'))->toBe(1800);
});

it('returns outstanding claims data as JSON', function () {
    $provider = InsuranceProvider::factory()->create(['name' => 'Outstanding Provider']);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->count(2)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'submitted',
        'total_claim_amount' => 1000,
        'approved_amount' => 900,
        'payment_amount' => 0,
        'submitted_at' => now()->subDays(15),
        'created_at' => now()->subDays(15),
    ]);

    InsuranceClaim::factory()->create([
        'patient_insurance_id' => $patientInsurance->id,
        'status' => 'approved',
        'total_claim_amount' => 2000,
        'approved_amount' => 1800,
        'payment_amount' => 0,
        'submitted_at' => now()->subDays(45),
        'created_at' => now()->subDays(45),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/admin/insurance/reports/outstanding-claims');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'total_outstanding',
            'total_claims',
            'aging_analysis',
            'by_provider',
        ],
    ]);

    expect($response->json('data.total_claims'))->toBe(3);
    // All 3 claims should be in aging buckets based on days outstanding
    expect($response->json('data.aging_analysis'))->toHaveKey('0-30');
    expect($response->json('data.aging_analysis'))->toHaveKey('31-60');
});

it('filters claims summary by date range', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    // Claims in current month
    InsuranceClaim::factory()->count(2)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'date_of_attendance' => now(),
    ]);

    // Claims in previous month
    InsuranceClaim::factory()->count(3)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'date_of_attendance' => now()->subMonth(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/admin/insurance/reports/claims-summary', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
        ]);

    $response->assertSuccessful();
    expect($response->json('data.total_claims'))->toBe(2);
});

it('accepts provider filter parameter', function () {
    $provider = InsuranceProvider::factory()->create(['name' => 'Test Provider']);
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    $patient = Patient::factory()->create();
    $patientInsurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $plan->id,
    ]);

    InsuranceClaim::factory()->count(2)->create([
        'patient_insurance_id' => $patientInsurance->id,
        'date_of_attendance' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/admin/insurance/reports/claims-summary', [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->endOfMonth()->toDateString(),
            'provider_id' => $provider->id,
        ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            'total_claims',
            'total_claimed_amount',
            'claims_by_provider',
        ],
    ]);
});

it('requires admin permission to access dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get('/admin/insurance/reports');

    $response->assertForbidden();
});

it('requires admin permission for API endpoints', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->getJson('/admin/insurance/reports/claims-summary');

    $response->assertForbidden();
});
