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
