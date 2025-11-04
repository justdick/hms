<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);
});

it('displays coverage rules index page', function () {
    InsuranceCoverageRule::factory()->count(3)->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    $response = $this->get(route('admin.insurance.coverage-rules.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/CoverageRules/Index')
        ->has('rules.data', 3)
        ->has('plans')
    );
});

it('filters coverage rules by plan', function () {
    $anotherPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    InsuranceCoverageRule::factory()->count(2)->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $anotherPlan->id,
    ]);

    $response = $this->get(route('admin.insurance.coverage-rules.index', [
        'plan_id' => $this->plan->id,
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('rules.data', 2)
    );
});

it('creates a new coverage rule', function () {
    $data = [
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'item_description' => 'Paracetamol 500mg',
        'is_covered' => true,
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'patient_copay_percentage' => 20.00,
        'is_active' => true,
    ];

    $response = $this->post(route('admin.insurance.coverage-rules.store'), $data);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Coverage rule created successfully.');

    $this->assertDatabaseHas('insurance_coverage_rules', [
        'item_code' => 'DRUG001',
        'coverage_value' => 80.00,
    ]);
});

it('validates required fields when creating coverage rule', function () {
    $response = $this->post(route('admin.insurance.coverage-rules.store'), []);

    $response->assertSessionHasErrors([
        'insurance_plan_id',
        'coverage_category',
        'coverage_type',
    ]);
});

it('updates a coverage rule', function () {
    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_value' => 70.00,
    ]);

    $response = $this->put(route('admin.insurance.coverage-rules.update', $rule), [
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => $rule->coverage_category,
        'item_code' => $rule->item_code,
        'coverage_type' => 'percentage',
        'coverage_value' => 90.00,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Coverage rule updated successfully.');

    $this->assertDatabaseHas('insurance_coverage_rules', [
        'id' => $rule->id,
        'coverage_value' => 90.00,
    ]);
});

it('deletes a coverage rule', function () {
    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    $response = $this->delete(route('admin.insurance.coverage-rules.destroy', $rule));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Coverage rule deleted successfully.');

    $this->assertDatabaseMissing('insurance_coverage_rules', ['id' => $rule->id]);
});

it('performs quick update on coverage rule', function () {
    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_value' => 80.00,
        'patient_copay_percentage' => 20.00,
    ]);

    $response = $this->patchJson(route('admin.insurance.coverage-rules.quick-update', $rule), [
        'coverage_value' => 90.00,
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
    ]);

    $this->assertDatabaseHas('insurance_coverage_rules', [
        'id' => $rule->id,
        'coverage_value' => 90.00,
        'patient_copay_percentage' => 10.00,
    ]);
});

it('validates coverage value range in quick update', function () {
    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    $response = $this->patchJson(route('admin.insurance.coverage-rules.quick-update', $rule), [
        'coverage_value' => 150.00,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['coverage_value']);
});

it('requires coverage value in quick update', function () {
    $rule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
    ]);

    $response = $this->patchJson(route('admin.insurance.coverage-rules.quick-update', $rule), []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['coverage_value']);
});

it('prevents creating duplicate exception for same item in same category', function () {
    // Create an existing exception
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'item_description' => 'Paracetamol 500mg',
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
    ]);

    // Try to create another exception for the same item
    $response = $this->post(route('admin.insurance.coverage-rules.store'), [
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'item_description' => 'Paracetamol 500mg',
        'coverage_type' => 'percentage',
        'coverage_value' => 90.00,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['item_code']);
    $response->assertSessionHasErrorsIn('default', [
        'item_code' => 'This item already has a coverage exception. Please edit the existing exception instead.',
    ]);
});

it('allows creating exception for same item code in different category', function () {
    // Create an existing exception in drug category
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'CODE001',
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
    ]);

    // Create exception for same code in lab category (should succeed)
    $response = $this->post(route('admin.insurance.coverage-rules.store'), [
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => 'CODE001',
        'item_description' => 'Lab Test',
        'coverage_type' => 'percentage',
        'coverage_value' => 90.00,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(InsuranceCoverageRule::where('item_code', 'CODE001')->count())->toBe(2);
});

it('allows creating exception for same item code in different plan', function () {
    $anotherPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    // Create an existing exception in first plan
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
    ]);

    // Create exception for same item in different plan (should succeed)
    $response = $this->post(route('admin.insurance.coverage-rules.store'), [
        'insurance_plan_id' => $anotherPlan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'item_description' => 'Paracetamol 500mg',
        'coverage_type' => 'percentage',
        'coverage_value' => 90.00,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(InsuranceCoverageRule::where('item_code', 'DRUG001')->count())->toBe(2);
});

it('allows creating general rule even if specific rules exist', function () {
    // Create a specific exception
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
    ]);

    // Create general rule for same category (should succeed)
    $response = $this->post(route('admin.insurance.coverage-rules.store'), [
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_type' => 'percentage',
        'coverage_value' => 70.00,
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

it('returns category exceptions via API endpoint', function () {
    // Create general rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 70.00,
    ]);

    // Create specific exceptions
    InsuranceCoverageRule::factory()->count(3)->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => fn () => 'DRUG'.fake()->unique()->numberBetween(100, 999),
        'coverage_value' => 80.00,
    ]);

    // Create exception in different category (should not be returned)
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => 'LAB001',
        'coverage_value' => 90.00,
    ]);

    $response = $this->getJson(route('admin.insurance.plans.coverage.exceptions', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(3, 'exceptions');
    $response->assertJsonStructure([
        'exceptions' => [
            '*' => [
                'id',
                'item_code',
                'item_description',
                'coverage_type',
                'coverage_value',
            ],
        ],
    ]);
});
