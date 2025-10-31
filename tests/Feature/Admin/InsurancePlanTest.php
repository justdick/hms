<?php

use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\PatientInsurance;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->provider = InsuranceProvider::factory()->create();
});

it('displays insurance plans index page', function () {
    InsurancePlan::factory()->count(3)->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    $response = $this->get(route('admin.insurance.plans.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/Index')
        ->has('plans.data', 3)
    );
});

it('displays create insurance plan page', function () {
    $response = $this->get(route('admin.insurance.plans.create'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/Create')
        ->has('providers')
    );
});

it('creates a new insurance plan', function () {
    $data = [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Gold Plan',
        'plan_code' => 'GOLD001',
        'plan_type' => 'corporate',
        'coverage_type' => 'comprehensive',
        'annual_limit' => 1000000.00,
        'visit_limit' => 50,
        'default_copay_percentage' => 10.00,
        'requires_referral' => false,
        'is_active' => true,
        'effective_from' => '2025-01-01',
        'effective_to' => '2025-12-31',
        'description' => 'Premium coverage plan',
    ];

    $response = $this->post(route('admin.insurance.plans.store'), $data);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance plan created successfully.');

    $this->assertDatabaseHas('insurance_plans', [
        'plan_name' => 'Gold Plan',
        'plan_code' => 'GOLD001',
    ]);
});

it('validates required fields when creating plan', function () {
    $response = $this->post(route('admin.insurance.plans.store'), []);

    $response->assertSessionHasErrors([
        'insurance_provider_id',
        'plan_name',
        'plan_code',
        'plan_type',
        'coverage_type',
    ]);
});

it('validates enum values for plan type and coverage type', function () {
    $response = $this->post(route('admin.insurance.plans.store'), [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
        'plan_code' => 'TEST001',
        'plan_type' => 'invalid_type',
        'coverage_type' => 'invalid_coverage',
    ]);

    $response->assertSessionHasErrors(['plan_type', 'coverage_type']);
});

it('displays insurance plan show page', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    $response = $this->get(route('admin.insurance.plans.show', $plan));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/Show')
        ->has('plan')
        ->where('plan.id', $plan->id)
    );
});

it('updates an insurance plan', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Original Plan',
    ]);

    $response = $this->put(route('admin.insurance.plans.update', $plan), [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Updated Plan',
        'plan_code' => $plan->plan_code,
        'plan_type' => 'family',
        'coverage_type' => 'outpatient',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance plan updated successfully.');

    $this->assertDatabaseHas('insurance_plans', [
        'id' => $plan->id,
        'plan_name' => 'Updated Plan',
    ]);
});

it('deletes an insurance plan without enrolled patients', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    $response = $this->delete(route('admin.insurance.plans.destroy', $plan));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance plan deleted successfully.');

    $this->assertDatabaseMissing('insurance_plans', ['id' => $plan->id]);
});

it('prevents deleting plan with enrolled patients', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    PatientInsurance::factory()->create([
        'insurance_plan_id' => $plan->id,
    ]);

    $response = $this->delete(route('admin.insurance.plans.destroy', $plan));

    $response->assertSessionHas('error', 'Cannot delete plan with enrolled patients.');

    $this->assertDatabaseHas('insurance_plans', ['id' => $plan->id]);
});
