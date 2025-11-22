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

it('renders coverage management page successfully', function () {
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();

    // Verify the correct Inertia component is rendered
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/CoverageManagement')
        ->has('plan.data')
        ->has('categories')
    );
});

it('provides plan data to coverage management page', function () {
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();

    // Verify plan data is passed to the component
    $response->assertInertia(fn ($page) => $page
        ->where('plan.data.id', $this->plan->id)
        ->where('plan.data.plan_name', $this->plan->plan_name)
    );
});

it('provides all six coverage categories', function () {
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();

    // Verify all 6 categories are provided
    $response->assertInertia(fn ($page) => $page
        ->has('categories', 6)
        ->where('categories.0.category', 'consultation')
        ->where('categories.1.category', 'drug')
        ->where('categories.2.category', 'lab')
        ->where('categories.3.category', 'procedure')
        ->where('categories.4.category', 'ward')
        ->where('categories.5.category', 'nursing')
    );
});

it('allows authorized users to access coverage management', function () {
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();
});

// Test for global search functionality
it('loads exceptions for searching across categories', function () {
    // Create some coverage exceptions
    $drugRule = \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA-500',
        'item_description' => 'Paracetamol 500mg',
        'coverage_value' => 100,
    ]);

    $labRule = \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => 'CBC-001',
        'item_description' => 'Complete Blood Count',
        'coverage_value' => 90,
    ]);

    // Request exceptions for drug category
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage/drug/exceptions");

    $response->assertOk();
    $response->assertJson([
        'exceptions' => [
            [
                'item_code' => 'PARA-500',
                'item_description' => 'Paracetamol 500mg',
            ],
        ],
    ]);
});

// Test for color coding consistency
it('provides correct coverage data for color coding', function () {
    // Create coverage rules with different percentages
    \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'consultation',
        'item_code' => null, // Default rule
        'coverage_value' => 85, // Green (80-100%)
    ]);

    \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 65, // Yellow (50-79%)
    ]);

    \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_value' => 30, // Red (1-49%)
    ]);

    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();

    // Verify coverage values are provided for color coding
    $response->assertInertia(fn ($page) => $page
        ->where('categories.0.default_coverage', 85)
        ->where('categories.1.default_coverage', 65)
        ->where('categories.2.default_coverage', 30)
    );
});

// Test for exception count badges
it('provides exception counts for badge display', function () {
    // Create default rules
    \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Create exceptions
    \App\Models\InsuranceCoverageRule::factory()->count(3)->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG-001',
        'coverage_value' => 100,
    ]);

    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertOk();

    // Verify exception count is provided
    $response->assertInertia(fn ($page) => $page
        ->where('categories.1.exception_count', 3)
    );
});

// Test for simplified expanded content
it('provides only necessary data for expanded cards', function () {
    // Create a default rule and exceptions
    \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    $exception = \App\Models\InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA-500',
        'item_description' => 'Paracetamol 500mg',
        'coverage_value' => 100,
    ]);

    // Request exceptions for the category
    $response = actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage/drug/exceptions");

    $response->assertOk();

    // Verify only exception data is returned (no nested panels)
    $response->assertJsonStructure([
        'exceptions' => [
            '*' => [
                'id',
                'item_code',
                'item_description',
                'coverage_value',
                'patient_copay_percentage',
            ],
        ],
    ]);
});
