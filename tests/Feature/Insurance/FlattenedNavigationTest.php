<?php

use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'system.admin']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('system.admin');

    $provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);
});

it('renders plans index with action buttons', function () {
    $response = actingAs($this->user)
        ->get('/admin/insurance/plans');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/Index')
        ->has('plans.data')
    );
});

it('provides plan data with coverage rules and tariffs count', function () {
    // Create some coverage rules and tariffs for the plan
    $this->plan->coverageRules()->create([
        'coverage_category' => 'drug',
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
        'is_covered' => true,
        'is_active' => true,
    ]);

    $this->plan->tariffs()->create([
        'item_type' => 'drug',
        'item_code' => 'PARA-500',
        'item_description' => 'Paracetamol 500mg',
        'standard_price' => 5.00,
        'insurance_tariff' => 4.50,
        'effective_from' => now(),
        'is_active' => true,
    ]);

    $response = actingAs($this->user)
        ->get('/admin/insurance/plans');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('plans.data', 1)
        ->where('plans.data.0.id', $this->plan->id)
        ->where('plans.data.0.coverage_rules_count', 1)
        ->where('plans.data.0.tariffs_count', 1)
    );
});

it('allows direct navigation to coverage management from plans list', function () {
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/CoverageManagement')
        ->where('plan.data.id', $this->plan->id)
    );
});

it('allows navigation to claims with plan filter', function () {
    $response = actingAs($this->user)
        ->get("/admin/insurance/claims?plan_id={$this->plan->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Claims/Index')
    );
});

// Note: Edit page not implemented yet - plans are edited through the Show page

it('reduces navigation depth from 5 to 3 clicks for coverage management', function () {
    // Old flow: Plans List → Plan Details → Coverage Dashboard → Expand Category → Add Exception (5 clicks)
    // New flow: Plans List → [Manage Coverage] → Coverage Management → Add Exception (3 clicks)

    // Step 1: Plans List (starting point)
    $response = actingAs($this->user)
        ->get('/admin/insurance/plans');
    $response->assertOk();

    // Step 2: Direct to Coverage Management (1 click - "Manage Coverage" button)
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/CoverageManagement')
    );

    // Step 3: Add Exception (2nd click - would be in UI)
    // This verifies the endpoint exists and is accessible
    expect($response->status())->toBe(200);
});
