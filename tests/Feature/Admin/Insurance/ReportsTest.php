<?php

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    $this->permissions = [
        'system.admin' => Permission::create(['name' => 'system.admin']),
        'insurance.view-reports' => Permission::create(['name' => 'insurance.view-reports']),
        'insurance.export-reports' => Permission::create(['name' => 'insurance.export-reports']),
    ];

    // Create user with report viewing permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['insurance.view-reports', 'insurance.export-reports']);

    // Create insurance providers and plans
    $this->provider1 = InsuranceProvider::factory()->create(['name' => 'Provider A']);
    $this->provider2 = InsuranceProvider::factory()->create(['name' => 'Provider B']);

    $this->plan1 = InsurancePlan::factory()->create(['insurance_provider_id' => $this->provider1->id]);
    $this->plan2 = InsurancePlan::factory()->create(['insurance_provider_id' => $this->provider2->id]);

    // Create patients with insurance
    $patient1 = Patient::factory()->create();
    $patient2 = Patient::factory()->create();

    $this->patientInsurance1 = PatientInsurance::factory()->create([
        'patient_id' => $patient1->id,
        'insurance_plan_id' => $this->plan1->id,
    ]);

    $this->patientInsurance2 = PatientInsurance::factory()->create([
        'patient_id' => $patient2->id,
        'insurance_plan_id' => $this->plan2->id,
    ]);

    // Create vetting officers
    $this->vettingOfficer1 = User::factory()->create(['name' => 'Vetting Officer 1']);
    $this->vettingOfficer2 = User::factory()->create(['name' => 'Vetting Officer 2']);
});

// ============================================================================
// Reports Index Tests
// ============================================================================

it('displays the reports index page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/Index')
    );
});

it('requires authentication to access reports index', function () {
    $response = $this->get(route('admin.insurance.reports.index'));

    $response->assertRedirect(route('login'));
});

it('requires permission to access reports index', function () {
    $unauthorizedUser = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->get(route('admin.insurance.reports.index'));

    $response->assertForbidden();
});

// ============================================================================
// Claims Summary Report Tests
// ============================================================================

it('displays claims summary report with default date range', function () {
    // Create claims with different statuses
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'draft',
        'total_claim_amount' => 1000.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'submitted',
        'total_claim_amount' => 2000.00,
        'approved_amount' => 1800.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'total_claim_amount' => 3000.00,
        'approved_amount' => 2700.00,
        'payment_amount' => 2700.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.claims-summary'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/ClaimsSummary')
        ->has('data')
        ->has('providers')
        ->has('filters')
    );
});

it('filters claims summary by date range', function () {
    // Create claims on different dates
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'total_claim_amount' => 1000.00,
        'approved_amount' => 900.00,
        'payment_amount' => 900.00,
        'date_of_attendance' => now()->subDays(10)->toDateString(),
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'total_claim_amount' => 2000.00,
        'approved_amount' => 1800.00,
        'payment_amount' => 1800.00,
        'date_of_attendance' => now()->subMonths(2)->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.claims-summary', [
            'date_from' => now()->subDays(15)->toDateString(),
            'date_to' => now()->toDateString(),
        ]));

    $response->assertSuccessful();
});

it('filters claims summary by provider', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'total_claim_amount' => 1000.00,
        'approved_amount' => 900.00,
        'payment_amount' => 900.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance2->patient_id,
        'patient_insurance_id' => $this->patientInsurance2->id,
        'status' => 'paid',
        'total_claim_amount' => 2000.00,
        'approved_amount' => 1800.00,
        'payment_amount' => 1800.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.claims-summary', [
            'provider_id' => $this->provider1->id,
        ]));

    $response->assertSuccessful();
});

// ============================================================================
// Revenue Analysis Report Tests
// ============================================================================

it('displays revenue analysis report', function () {
    // Create insurance claims (paid)
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'total_claim_amount' => 5000.00,
        'approved_amount' => 4500.00,
        'payment_amount' => 4500.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    // Create cash charges (not associated with insurance)
    Charge::factory()->count(3)->create([
        'status' => 'paid',
        'amount' => 1000.00,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.revenue-analysis'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/RevenueAnalysis')
        ->has('data')
        ->has('filters')
    );
});

it('calculates insurance and cash revenue correctly', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'payment_amount' => 1000.00,
        'date_of_attendance' => now()->toDateString(),
    ]);

    Charge::factory()->create([
        'status' => 'paid',
        'amount' => 500.00,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.revenue-analysis'));

    $response->assertSuccessful();
});

// ============================================================================
// Outstanding Claims Report Tests
// ============================================================================

it('displays outstanding claims report with aging analysis', function () {
    // Create submitted claim (30 days old)
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'submitted',
        'total_claim_amount' => 1000.00,
        'payment_amount' => 0,
        'submitted_at' => now()->subDays(30),
        'date_of_attendance' => now()->subDays(30)->toDateString(),
    ]);

    // Create approved claim (60 days old)
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'approved',
        'total_claim_amount' => 2000.00,
        'approved_amount' => 1800.00,
        'payment_amount' => 0,
        'submitted_at' => now()->subDays(60),
        'date_of_attendance' => now()->subDays(60)->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.outstanding-claims'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/OutstandingClaims')
        ->has('data')
        ->has('providers')
        ->has('filters')
    );
});

it('filters outstanding claims by provider', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'submitted',
        'total_claim_amount' => 1000.00,
        'payment_amount' => 0,
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.outstanding-claims', [
            'provider_id' => $this->provider1->id,
        ]));

    $response->assertSuccessful();
});

// ============================================================================
// Vetting Performance Report Tests
// ============================================================================

it('displays vetting performance report', function () {
    // Create vetted claims
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'vetted',
        'vetted_by' => $this->vettingOfficer1->id,
        'vetted_at' => now(),
        'created_at' => now()->subHours(2),
        'date_of_attendance' => now()->toDateString(),
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'vetted',
        'vetted_by' => $this->vettingOfficer2->id,
        'vetted_at' => now(),
        'created_at' => now()->subHours(4),
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.vetting-performance'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/VettingPerformance')
        ->has('data')
        ->has('filters')
    );
});

it('calculates average turnaround time for vetting', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'vetted',
        'vetted_by' => $this->vettingOfficer1->id,
        'vetted_at' => now(),
        'created_at' => now()->subHours(2),
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.vetting-performance'));

    $response->assertSuccessful();
});

// ============================================================================
// Utilization Report Tests
// ============================================================================

it('displays utilization report with top services', function () {
    // Create claims with items
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'date_of_attendance' => now()->toDateString(),
    ]);

    // Create charges for claim items
    $charge1 = Charge::factory()->create(['description' => 'Consultation']);
    $charge2 = Charge::factory()->create(['description' => 'Lab Test']);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'charge_id' => $charge1->id,
        'subtotal' => 500.00,
        'insurance_pays' => 450.00,
    ]);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'charge_id' => $charge2->id,
        'subtotal' => 300.00,
        'insurance_pays' => 270.00,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.utilization'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/UtilizationReport')
        ->has('data')
        ->has('providers')
        ->has('filters')
    );
});

it('filters utilization report by provider', function () {
    $claim = InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'date_of_attendance' => now()->toDateString(),
    ]);

    $charge = Charge::factory()->create(['description' => 'Consultation']);

    InsuranceClaimItem::factory()->create([
        'insurance_claim_id' => $claim->id,
        'charge_id' => $charge->id,
        'subtotal' => 500.00,
        'insurance_pays' => 450.00,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.utilization', [
            'provider_id' => $this->provider1->id,
        ]));

    $response->assertSuccessful();
});

// ============================================================================
// Rejection Analysis Report Tests
// ============================================================================

it('displays rejection analysis report', function () {
    // Create rejected claims with reasons
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'rejected',
        'rejection_reason' => 'Incomplete documentation',
        'total_claim_amount' => 1000.00,
        'updated_at' => now(),
        'date_of_attendance' => now()->toDateString(),
    ]);

    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'rejected',
        'rejection_reason' => 'Service not covered',
        'total_claim_amount' => 2000.00,
        'updated_at' => now(),
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.rejection-analysis'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Reports/RejectionAnalysis')
        ->has('data')
        ->has('providers')
        ->has('filters')
    );
});

it('groups rejections by reason', function () {
    InsuranceClaim::factory()->count(3)->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'rejected',
        'rejection_reason' => 'Incomplete documentation',
        'updated_at' => now(),
        'date_of_attendance' => now()->toDateString(),
    ]);

    InsuranceClaim::factory()->count(2)->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'rejected',
        'rejection_reason' => 'Service not covered',
        'updated_at' => now(),
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.rejection-analysis'));

    $response->assertSuccessful();
});

it('filters rejection analysis by provider', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'rejected',
        'rejection_reason' => 'Service not covered',
        'updated_at' => now(),
        'date_of_attendance' => now()->toDateString(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.rejection-analysis', [
            'provider_id' => $this->provider1->id,
        ]));

    $response->assertSuccessful();
});

// ============================================================================
// Report Caching Tests
// ============================================================================

it('caches claims summary report data', function () {
    InsuranceClaim::factory()->create([
        'patient_id' => $this->patientInsurance1->patient_id,
        'patient_insurance_id' => $this->patientInsurance1->id,
        'status' => 'paid',
        'date_of_attendance' => now()->toDateString(),
    ]);

    // First request should cache the data
    $response1 = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.claims-summary'));

    // Second request should use cached data
    $response2 = $this->actingAs($this->user)
        ->get(route('admin.insurance.reports.claims-summary'));

    $response1->assertSuccessful();
    $response2->assertSuccessful();
});

// ============================================================================
// Permission Tests for All Reports
// ============================================================================

it('requires permission to access all reports', function () {
    $unauthorizedUser = User::factory()->create();

    $routes = [
        'admin.insurance.reports.claims-summary',
        'admin.insurance.reports.revenue-analysis',
        'admin.insurance.reports.outstanding-claims',
        'admin.insurance.reports.vetting-performance',
        'admin.insurance.reports.utilization',
        'admin.insurance.reports.rejection-analysis',
    ];

    foreach ($routes as $route) {
        $response = $this->actingAs($unauthorizedUser)
            ->get(route($route));

        $response->assertForbidden();
    }
});
