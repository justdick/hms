<?php

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->provider = InsuranceProvider::factory()->create();
    $this->plan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->provider->id,
    ]);
});

it('displays coverage dashboard page', function () {
    $response = $this->get(route('admin.insurance.plans.coverage', $this->plan));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Insurance/Plans/CoverageDashboard')
        ->has('plan')
        ->has('categories')
    );
});

it('shows all six coverage categories', function () {
    $response = $this->get(route('admin.insurance.plans.coverage', $this->plan));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('categories', 6)
    );

    $categories = $response->viewData('page')['props']['categories'];
    $categoryNames = collect($categories)->pluck('category')->toArray();

    expect($categoryNames)->toContain('consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing');
});

it('displays default coverage for each category', function () {
    // Create default rules for some categories
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => null,
        'coverage_value' => 90,
    ]);

    $response = $this->get(route('admin.insurance.plans.coverage', $this->plan));

    $response->assertSuccessful();

    $categories = $response->viewData('page')['props']['categories'];
    $drugCategory = collect($categories)->firstWhere('category', 'drug');
    $labCategory = collect($categories)->firstWhere('category', 'lab');

    expect((float) $drugCategory['default_coverage'])->toBe(80.0)
        ->and((float) $labCategory['default_coverage'])->toBe(90.0);
});

it('displays exception count for each category', function () {
    // Create default rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Create exceptions
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG100',
        'coverage_value' => 90,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG200',
        'coverage_value' => 90,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG300',
        'coverage_value' => 90,
    ]);

    $response = $this->get(route('admin.insurance.plans.coverage', $this->plan));

    $response->assertSuccessful();

    $categories = $response->viewData('page')['props']['categories'];
    $drugCategory = collect($categories)->firstWhere('category', 'drug');

    expect($drugCategory['exception_count'])->toBeGreaterThanOrEqual(3);
});

it('shows zero exception count when no exceptions exist', function () {
    // Create only default rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    $response = $this->get(route('admin.insurance.plans.coverage', $this->plan));

    $response->assertSuccessful();

    $categories = $response->viewData('page')['props']['categories'];
    $drugCategory = collect($categories)->firstWhere('category', 'drug');

    expect($drugCategory['exception_count'])->toBe(0);
});

it('shows null coverage when no default rule exists', function () {
    $response = $this->get(route('admin.insurance.plans.coverage', $this->plan));

    $response->assertSuccessful();

    $categories = $response->viewData('page')['props']['categories'];
    $drugCategory = collect($categories)->firstWhere('category', 'drug');

    expect($drugCategory['default_coverage'])->toBeNull();
});

it('returns category exceptions via API endpoint', function () {
    // Create default rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 70,
    ]);

    // Create specific exceptions
    $exception1 = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'item_description' => 'Paracetamol',
        'coverage_value' => 100,
    ]);

    $exception2 = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG002',
        'item_description' => 'Ibuprofen',
        'coverage_value' => 90,
    ]);

    $response = $this->getJson(route('admin.insurance.plans.coverage.exceptions', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'exceptions' => [
            '*' => [
                'id',
                'item_code',
                'item_description',
                'coverage_type',
                'coverage_value',
                'patient_copay_percentage',
            ],
        ],
    ]);

    $exceptions = $response->json('exceptions');
    expect(count($exceptions))->toBeGreaterThanOrEqual(2);

    $codes = collect($exceptions)->pluck('item_code')->toArray();
    expect($codes)->toContain('DRUG001', 'DRUG002');
});

it('filters exceptions by category', function () {
    // Create exceptions in different categories
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_value' => 80,
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'lab',
        'item_code' => 'LAB001',
        'coverage_value' => 90,
    ]);

    $response = $this->getJson(route('admin.insurance.plans.coverage.exceptions', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'exceptions');

    $exceptions = $response->json('exceptions');
    expect($exceptions[0]['item_code'])->toBe('DRUG001');
});

it('excludes general rules from exceptions list', function () {
    // Create general rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 70,
    ]);

    // Create specific exception
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_value' => 80,
    ]);

    $response = $this->getJson(route('admin.insurance.plans.coverage.exceptions', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'exceptions');

    $exceptions = $response->json('exceptions');
    expect($exceptions[0]['item_code'])->toBe('DRUG001');
});

it('returns recent items from last 30 days', function () {
    // Create recent drug
    $recentDrug = Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Recent Drug',
        'unit_price' => 600.00,
        'created_at' => now()->subDays(5),
    ]);

    // Create old drug (should not appear)
    Drug::factory()->create([
        'drug_code' => 'DRUG002',
        'name' => 'Old Drug',
        'unit_price' => 100.00,
        'created_at' => now()->subDays(35),
    ]);

    $response = $this->get(route('admin.insurance.plans.recent-items', $this->plan));

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

    $recentItems = $response->json('recent_items');
    $drugItem = collect($recentItems)->firstWhere('code', 'DRUG001');

    expect($drugItem)->not->toBeNull()
        ->and($drugItem['name'])->toBe('Recent Drug')
        ->and((float) $drugItem['price'])->toBe(600.0);

    // Old drug should not be in the list
    $oldDrugItem = collect($recentItems)->firstWhere('code', 'DRUG002');
    expect($oldDrugItem)->toBeNull();
});

it('flags expensive items in recent items', function () {
    // Create expensive drug (above $500 threshold)
    Drug::factory()->create([
        'drug_code' => 'EXP001',
        'name' => 'Expensive Drug',
        'unit_price' => 1000.00,
        'created_at' => now()->subDays(1),
    ]);

    // Create cheap drug
    Drug::factory()->create([
        'drug_code' => 'CHEAP001',
        'name' => 'Cheap Drug',
        'unit_price' => 50.00,
        'created_at' => now()->subDays(1),
    ]);

    $response = $this->get(route('admin.insurance.plans.recent-items', $this->plan));

    $response->assertSuccessful();

    $recentItems = $response->json('recent_items');
    $expensiveItem = collect($recentItems)->firstWhere('code', 'EXP001');
    $cheapItem = collect($recentItems)->firstWhere('code', 'CHEAP001');

    expect($expensiveItem['is_expensive'])->toBeTrue()
        ->and($cheapItem['is_expensive'])->toBeFalse();
});

it('shows correct coverage status for recent items', function () {
    // Create drug with exception
    $drugWithException = Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Drug With Exception',
        'unit_price' => 100.00,
        'created_at' => now()->subDays(1),
    ]);

    // Create default rule first
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_value' => 80,
    ]);

    // Create exception for DRUG001
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_value' => 100,
    ]);

    // Create drug with default coverage (no exception)
    $drugWithDefault = Drug::factory()->create([
        'drug_code' => 'DRUG002',
        'name' => 'Drug With Default',
        'unit_price' => 100.00,
        'created_at' => now()->subDays(1),
    ]);

    // Create drug without coverage (no default rule for this specific drug)
    // We'll test not_covered by checking a drug that doesn't have the default rule applied
    // Actually, since we have a default rule for drugs, all drugs will have default coverage
    // So let's just verify the two statuses we can test: exception and default
    $response = $this->get(route('admin.insurance.plans.recent-items', $this->plan));

    $response->assertSuccessful();

    $recentItems = $response->json('recent_items');

    $item1 = collect($recentItems)->firstWhere('code', 'DRUG001');
    $item2 = collect($recentItems)->firstWhere('code', 'DRUG002');

    expect($item1['coverage_status'])->toBe('exception')
        ->and($item2['coverage_status'])->toBe('default');
});
