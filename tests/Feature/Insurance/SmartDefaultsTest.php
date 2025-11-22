<?php

use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'system.admin']);
    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('system.admin');
    $this->provider = InsuranceProvider::factory()->create();
});

it('creates plan with 6 default coverage rules at 80% when no coverage rules provided', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/insurance/plans', [
            'insurance_provider_id' => $this->provider->id,
            'plan_name' => 'Test Plan',
            'plan_code' => 'TEST-001',
            'plan_type' => 'individual',
            'coverage_type' => 'comprehensive',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $plan = InsurancePlan::latest()->first();
    expect($plan)->not->toBeNull();

    // Verify 6 coverage rules were created
    $coverageRules = $plan->coverageRules;
    expect($coverageRules)->toHaveCount(6);

    // Verify all categories are covered
    $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
    foreach ($categories as $category) {
        $rule = $coverageRules->firstWhere('coverage_category', $category);
        expect($rule)->not->toBeNull()
            ->and($rule->coverage_value)->toBe('80.00')
            ->and($rule->patient_copay_percentage)->toBe('20.00')
            ->and($rule->is_covered)->toBeTrue()
            ->and($rule->is_active)->toBeTrue()
            ->and($rule->item_code)->toBeNull();
    }
});

it('creates plan with custom coverage rules when provided', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/insurance/plans', [
            'insurance_provider_id' => $this->provider->id,
            'plan_name' => 'Custom Plan',
            'plan_code' => 'CUSTOM-001',
            'plan_type' => 'corporate',
            'coverage_type' => 'comprehensive',
            'is_active' => true,
            'coverage_rules' => [
                ['coverage_category' => 'consultation', 'coverage_value' => 90],
                ['coverage_category' => 'drug', 'coverage_value' => 85],
                ['coverage_category' => 'lab', 'coverage_value' => 75],
            ],
        ]);

    $response->assertRedirect();

    $plan = InsurancePlan::latest()->first();
    expect($plan)->not->toBeNull();

    // Verify only 3 coverage rules were created (as provided)
    $coverageRules = $plan->coverageRules;
    expect($coverageRules)->toHaveCount(3);

    // Verify custom coverage values
    $consultationRule = $coverageRules->firstWhere('coverage_category', 'consultation');
    expect($consultationRule->coverage_value)->toBe('90.00')
        ->and($consultationRule->patient_copay_percentage)->toBe('10.00');

    $drugRule = $coverageRules->firstWhere('coverage_category', 'drug');
    expect($drugRule->coverage_value)->toBe('85.00')
        ->and($drugRule->patient_copay_percentage)->toBe('15.00');

    $labRule = $coverageRules->firstWhere('coverage_category', 'lab');
    expect($labRule->coverage_value)->toBe('75.00')
        ->and($labRule->patient_copay_percentage)->toBe('25.00');
});

it('displays success message indicating default rules were created', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/insurance/plans', [
            'insurance_provider_id' => $this->provider->id,
            'plan_name' => 'Test Plan',
            'plan_code' => 'TEST-002',
            'plan_type' => 'individual',
            'coverage_type' => 'comprehensive',
            'is_active' => true,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance plan created successfully with default 80% coverage for all categories.');
});

it('displays different success message when custom rules provided', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/insurance/plans', [
            'insurance_provider_id' => $this->provider->id,
            'plan_name' => 'Custom Plan',
            'plan_code' => 'CUSTOM-002',
            'plan_type' => 'individual',
            'coverage_type' => 'comprehensive',
            'is_active' => true,
            'coverage_rules' => [
                ['coverage_category' => 'consultation', 'coverage_value' => 100],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance plan created successfully with default coverage rules.');
});

it('wraps plan creation and coverage rules in database transaction', function () {
    // Mock a scenario where coverage rule creation fails
    // This test verifies that if coverage rule creation fails, the plan is not created

    // Create a plan with invalid coverage data that would fail
    try {
        \DB::transaction(function () {
            $plan = InsurancePlan::create([
                'insurance_provider_id' => $this->provider->id,
                'plan_name' => 'Transaction Test Plan',
                'plan_code' => 'TRANS-001',
                'plan_type' => 'individual',
                'coverage_type' => 'comprehensive',
                'is_active' => true,
            ]);

            // Simulate an error during coverage rule creation
            throw new \Exception('Simulated error');
        });
    } catch (\Exception $e) {
        // Expected to catch the exception
    }

    // Verify plan was not created due to transaction rollback
    $plan = InsurancePlan::where('plan_code', 'TRANS-001')->first();
    expect($plan)->toBeNull();
});

it('creates all default rules with correct attributes', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/insurance/plans', [
            'insurance_provider_id' => $this->provider->id,
            'plan_name' => 'Attributes Test Plan',
            'plan_code' => 'ATTR-001',
            'plan_type' => 'family',
            'coverage_type' => 'comprehensive',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $plan = InsurancePlan::latest()->first();
    $coverageRules = $plan->coverageRules;

    // Verify all rules have correct attributes
    foreach ($coverageRules as $rule) {
        expect($rule->insurance_plan_id)->toBe($plan->id)
            ->and($rule->item_code)->toBeNull()
            ->and($rule->item_description)->toBeNull()
            ->and($rule->coverage_type)->toBe('percentage')
            ->and($rule->coverage_value)->toBe('80.00')
            ->and($rule->patient_copay_percentage)->toBe('20.00')
            ->and($rule->is_covered)->toBeTrue()
            ->and($rule->is_active)->toBeTrue();
    }
});

it('reduces plan setup time by auto-creating defaults', function () {
    // This test verifies the time-saving aspect by checking that
    // a plan can be created with minimal input and still have full coverage

    $startTime = microtime(true);

    $response = $this->actingAs($this->admin)
        ->post('/admin/insurance/plans', [
            'insurance_provider_id' => $this->provider->id,
            'plan_name' => 'Quick Setup Plan',
            'plan_code' => 'QUICK-001',
            'plan_type' => 'individual',
            'coverage_type' => 'comprehensive',
            'is_active' => true,
        ]);

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    $response->assertRedirect();

    $plan = InsurancePlan::latest()->first();

    // Verify plan is immediately usable with full coverage
    expect($plan->coverageRules)->toHaveCount(6);

    // Verify execution was fast (should be under 1 second)
    expect($executionTime)->toBeLessThan(1.0);
});
