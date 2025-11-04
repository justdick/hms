<?php

use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->user = User::factory()->create();
    $adminRole = Role::firstOrCreate(['name' => 'admin']);
    $this->user->assignRole($adminRole);

    $provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $provider->id,
    ]);

    // Create default coverage rules
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);
});

it('loads coverage dashboard successfully', function () {
    $response = $this->actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/CoverageDashboard')
        ->has('plan')
        ->has('categories')
    );
});

it('passes correct plan and categories data to dashboard', function () {
    $response = $this->actingAs($this->user)
        ->get("/admin/insurance/plans/{$this->plan->id}/coverage");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('plan.data.id', $this->plan->id)
        ->where('plan.data.plan_name', $this->plan->plan_name)
        ->has('categories', 6) // Should have all 6 categories
    );
});
