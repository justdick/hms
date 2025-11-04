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
        ->component('Admin/Insurance/Plans/CreateWithWizard')
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
    $response->assertSessionHas('success');

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
        ->has('plan.data')
        ->where('plan.data.id', $plan->id)
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

it('has require_explicit_approval_for_new_items attribute', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'require_explicit_approval_for_new_items' => true,
    ]);

    expect($plan->require_explicit_approval_for_new_items)->toBeTrue();
});

it('defaults require_explicit_approval_for_new_items to false', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    expect($plan->require_explicit_approval_for_new_items)->toBeFalse();
});

it('casts require_explicit_approval_for_new_items as boolean', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
        'require_explicit_approval_for_new_items' => 1,
    ]);

    expect($plan->require_explicit_approval_for_new_items)->toBeBool()
        ->and($plan->require_explicit_approval_for_new_items)->toBeTrue();
});

it('returns coverage presets', function () {
    $response = $this->get(route('admin.insurance.coverage-presets'));

    $response->assertSuccessful();
    $response->assertJson([
        'presets' => [
            [
                'id' => 'nhis_standard',
                'name' => 'NHIS Standard',
                'description' => 'Standard National Health Insurance coverage',
                'coverages' => [
                    'consultation' => 70,
                    'drug' => 80,
                    'lab' => 90,
                    'procedure' => 75,
                    'ward' => 100,
                    'nursing' => 80,
                ],
            ],
            [
                'id' => 'corporate_premium',
                'name' => 'Corporate Premium',
                'description' => 'High coverage for corporate clients',
                'coverages' => [
                    'consultation' => 90,
                    'drug' => 90,
                    'lab' => 100,
                    'procedure' => 90,
                    'ward' => 100,
                    'nursing' => 90,
                ],
            ],
            [
                'id' => 'basic',
                'name' => 'Basic Coverage',
                'description' => 'Minimal coverage plan',
                'coverages' => [
                    'consultation' => 50,
                    'drug' => 60,
                    'lab' => 70,
                    'procedure' => 50,
                    'ward' => 80,
                    'nursing' => 60,
                ],
            ],
            [
                'id' => 'custom',
                'name' => 'Custom',
                'description' => 'Configure your own coverage percentages',
                'coverages' => null,
            ],
        ],
    ]);
});

it('returns all four presets including custom', function () {
    $response = $this->get(route('admin.insurance.coverage-presets'));

    $response->assertSuccessful();
    $data = $response->json();

    expect($data['presets'])->toHaveCount(4)
        ->and($data['presets'][0]['id'])->toBe('nhis_standard')
        ->and($data['presets'][1]['id'])->toBe('corporate_premium')
        ->and($data['presets'][2]['id'])->toBe('basic')
        ->and($data['presets'][3]['id'])->toBe('custom')
        ->and($data['presets'][3]['coverages'])->toBeNull();
});

it('creates plan with coverage rules in transaction', function () {
    $data = [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Wizard Plan',
        'plan_code' => 'WIZ001',
        'plan_type' => 'corporate',
        'coverage_type' => 'comprehensive',
        'is_active' => true,
        'coverage_rules' => [
            [
                'coverage_category' => 'drug',
                'coverage_value' => 80,
            ],
            [
                'coverage_category' => 'lab',
                'coverage_value' => 90,
            ],
            [
                'coverage_category' => 'consultation',
                'coverage_value' => 70,
            ],
        ],
    ];

    $response = $this->post(route('admin.insurance.plans.store'), $data);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Insurance plan created successfully with default coverage rules.');

    $this->assertDatabaseHas('insurance_plans', [
        'plan_name' => 'Wizard Plan',
        'plan_code' => 'WIZ001',
    ]);

    $plan = InsurancePlan::where('plan_code', 'WIZ001')->first();

    expect($plan->coverageRules)->toHaveCount(3);

    $this->assertDatabaseHas('insurance_coverage_rules', [
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'drug',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
        'item_code' => null,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('insurance_coverage_rules', [
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'lab',
        'coverage_value' => 90,
        'patient_copay_percentage' => 10,
        'item_code' => null,
        'is_active' => true,
    ]);

    $this->assertDatabaseHas('insurance_coverage_rules', [
        'insurance_plan_id' => $plan->id,
        'coverage_category' => 'consultation',
        'coverage_value' => 70,
        'patient_copay_percentage' => 30,
        'item_code' => null,
        'is_active' => true,
    ]);
});

it('creates plan without coverage rules when none provided', function () {
    $data = [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Empty Plan',
        'plan_code' => 'EMPTY001',
        'plan_type' => 'individual',
        'coverage_type' => 'comprehensive',
        'is_active' => true,
    ];

    $response = $this->post(route('admin.insurance.plans.store'), $data);

    $response->assertRedirect();

    $plan = InsurancePlan::where('plan_code', 'EMPTY001')->first();

    expect($plan->coverageRules)->toHaveCount(0);
});

it('validates coverage rules category values', function () {
    $data = [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
        'plan_code' => 'TEST001',
        'plan_type' => 'individual',
        'coverage_type' => 'comprehensive',
        'is_active' => true,
        'coverage_rules' => [
            [
                'coverage_category' => 'invalid_category',
                'coverage_value' => 80,
            ],
        ],
    ];

    $response = $this->post(route('admin.insurance.plans.store'), $data);

    $response->assertSessionHasErrors(['coverage_rules.0.coverage_category']);
});

it('validates coverage rules percentage range', function () {
    $data = [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'Test Plan',
        'plan_code' => 'TEST001',
        'plan_type' => 'individual',
        'coverage_type' => 'comprehensive',
        'is_active' => true,
        'coverage_rules' => [
            [
                'coverage_category' => 'drug',
                'coverage_value' => 150,
            ],
        ],
    ];

    $response = $this->post(route('admin.insurance.plans.store'), $data);

    $response->assertSessionHasErrors(['coverage_rules.0.coverage_value']);
});

it('creates all six category rules when using preset', function () {
    $data = [
        'insurance_provider_id' => $this->provider->id,
        'plan_name' => 'NHIS Plan',
        'plan_code' => 'NHIS001',
        'plan_type' => 'corporate',
        'coverage_type' => 'comprehensive',
        'is_active' => true,
        'coverage_rules' => [
            ['coverage_category' => 'consultation', 'coverage_value' => 70],
            ['coverage_category' => 'drug', 'coverage_value' => 80],
            ['coverage_category' => 'lab', 'coverage_value' => 90],
            ['coverage_category' => 'procedure', 'coverage_value' => 75],
            ['coverage_category' => 'ward', 'coverage_value' => 100],
            ['coverage_category' => 'nursing', 'coverage_value' => 80],
        ],
    ];

    $response = $this->post(route('admin.insurance.plans.store'), $data);

    $response->assertRedirect();

    $plan = InsurancePlan::where('plan_code', 'NHIS001')->first();

    expect($plan->coverageRules)->toHaveCount(6);

    $categories = $plan->coverageRules->pluck('coverage_category')->toArray();
    expect($categories)->toContain('consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing');
});

it('returns recent items from last 30 days', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    // Create some recent drugs
    $recentDrug = \App\Models\Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Recent Drug',
        'unit_price' => 600.00,
        'created_at' => now()->subDays(5),
    ]);

    // Create an old drug (should not appear)
    \App\Models\Drug::factory()->create([
        'drug_code' => 'DRUG002',
        'name' => 'Old Drug',
        'unit_price' => 100.00,
        'created_at' => now()->subDays(35),
    ]);

    // Create a recent lab service
    $recentLab = \App\Models\LabService::factory()->create([
        'code' => 'LAB001',
        'name' => 'Recent Lab Test',
        'price' => 300.00,
        'created_at' => now()->subDays(10),
    ]);

    // Create a coverage rule for the drug
    $plan->coverageRules()->create([
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_type' => 'percentage',
        'coverage_value' => 90,
        'patient_copay_percentage' => 10,
        'is_covered' => true,
        'is_active' => true,
    ]);

    // Create a default rule for lab
    $plan->coverageRules()->create([
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
        'is_covered' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('admin.insurance.plans.recent-items', $plan));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'recent_items' => [
            '*' => [
                'id',
                'code',
                'name',
                'category',
                'price',
                'added_date',
                'is_expensive',
                'coverage_status',
            ],
        ],
    ]);

    $data = $response->json();

    // Should have at least 2 recent items (drug and lab, possibly more from billing services)
    expect($data['recent_items'])->toBeArray()
        ->and(count($data['recent_items']))->toBeGreaterThanOrEqual(2);

    // Find the drug item
    $drugItem = collect($data['recent_items'])->firstWhere('code', 'DRUG001');
    expect($drugItem)->not->toBeNull()
        ->and($drugItem['name'])->toBe('Recent Drug')
        ->and($drugItem['category'])->toBe('drug')
        ->and((float) $drugItem['price'])->toBe(600.0)
        ->and($drugItem['is_expensive'])->toBeTrue()
        ->and($drugItem['coverage_status'])->toBe('exception');

    // Find the lab item
    $labItem = collect($data['recent_items'])->firstWhere('code', 'LAB001');
    expect($labItem)->not->toBeNull()
        ->and($labItem['name'])->toBe('Recent Lab Test')
        ->and($labItem['category'])->toBe('lab')
        ->and((float) $labItem['price'])->toBe(300.0)
        ->and($labItem['is_expensive'])->toBeFalse()
        ->and($labItem['coverage_status'])->toBe('default');
});

it('flags expensive items above threshold', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    // Create an expensive drug
    \App\Models\Drug::factory()->create([
        'drug_code' => 'EXP001',
        'name' => 'Expensive Drug',
        'unit_price' => 1000.00,
        'created_at' => now()->subDays(1),
    ]);

    // Create a cheap drug
    \App\Models\Drug::factory()->create([
        'drug_code' => 'CHEAP001',
        'name' => 'Cheap Drug',
        'unit_price' => 50.00,
        'created_at' => now()->subDays(1),
    ]);

    $response = $this->get(route('admin.insurance.plans.recent-items', $plan));

    $response->assertSuccessful();

    $data = $response->json();

    $expensiveItem = collect($data['recent_items'])->firstWhere('code', 'EXP001');
    $cheapItem = collect($data['recent_items'])->firstWhere('code', 'CHEAP001');

    expect($expensiveItem['is_expensive'])->toBeTrue()
        ->and($cheapItem['is_expensive'])->toBeFalse();
});

it('returns not_covered status for items without coverage', function () {
    $plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);

    \App\Models\Drug::factory()->create([
        'drug_code' => 'UNCOV001',
        'name' => 'Uncovered Drug',
        'unit_price' => 100.00,
        'created_at' => now()->subDays(1),
    ]);

    $response = $this->get(route('admin.insurance.plans.recent-items', $plan));

    $response->assertSuccessful();

    $data = $response->json();
    $item = collect($data['recent_items'])->firstWhere('code', 'UNCOV001');

    expect($item['coverage_status'])->toBe('not_covered');
});
