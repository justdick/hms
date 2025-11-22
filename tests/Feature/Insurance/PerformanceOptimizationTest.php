<?php

use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'system.admin']);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('system.admin');
});

it('loads analytics dashboard within 3 seconds', function () {
    $this->actingAs($this->user);

    $startTime = microtime(true);

    $response = $this->get('/admin/insurance/reports');

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    $response->assertSuccessful();
    expect($loadTime)->toBeLessThan(3.0);
})->group('performance');

it('loads claims summary report with caching within 1 second on second request', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    InsuranceClaim::factory()->count(50)->create([
        'patient_insurance_id' => \App\Models\PatientInsurance::factory()->create([
            'insurance_plan_id' => $plan->id,
        ])->id,
    ]);

    $this->actingAs($this->user);

    // First request - populate cache
    $this->getJson('/admin/insurance/reports/claims-summary?date_from=2025-01-01&date_to=2025-01-31');

    // Second request - should use cache
    $startTime = microtime(true);

    $response = $this->getJson('/admin/insurance/reports/claims-summary?date_from=2025-01-01&date_to=2025-01-31');

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    $response->assertSuccessful();
    expect($loadTime)->toBeLessThan(1.0);
})->group('performance');

it('expands coverage management category within 1 second', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Create default coverage rules
    $categories = ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing'];
    foreach ($categories as $category) {
        $plan->coverageRules()->create([
            'coverage_category' => $category,
            'coverage_type' => 'percentage',
            'coverage_value' => 80.00,
            'patient_copay_percentage' => 20.00,
            'is_covered' => true,
            'is_active' => true,
        ]);
    }

    // Add some exceptions
    for ($i = 0; $i < 20; $i++) {
        $plan->coverageRules()->create([
            'coverage_category' => 'drug',
            'item_code' => "DRUG-{$i}",
            'item_description' => "Drug {$i}",
            'coverage_type' => 'percentage',
            'coverage_value' => 90.00,
            'patient_copay_percentage' => 10.00,
            'is_covered' => true,
            'is_active' => true,
        ]);
    }

    $this->actingAs($this->user);

    $startTime = microtime(true);

    $response = $this->getJson("/admin/insurance/plans/{$plan->id}/coverage/drug/exceptions");

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    $response->assertSuccessful();
    expect($loadTime)->toBeLessThan(1.0);
})->group('performance');

it('uses cache tags for easy invalidation', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    $plan->coverageRules()->create([
        'coverage_category' => 'drug',
        'coverage_type' => 'percentage',
        'coverage_value' => 80.00,
        'patient_copay_percentage' => 20.00,
        'is_covered' => true,
        'is_active' => true,
    ]);

    $this->actingAs($this->user);

    // First request - populate cache
    $response1 = $this->getJson("/admin/insurance/plans/{$plan->id}/coverage/drug/exceptions");
    $data1 = $response1->json('exceptions');

    // Add a new exception
    $plan->coverageRules()->create([
        'coverage_category' => 'drug',
        'item_code' => 'NEW-DRUG',
        'item_description' => 'New Drug',
        'coverage_type' => 'percentage',
        'coverage_value' => 90.00,
        'patient_copay_percentage' => 10.00,
        'is_covered' => true,
        'is_active' => true,
    ]);

    // Invalidate cache using tags
    Cache::tags(['insurance-plans', "plan-{$plan->id}", 'category-drug'])->flush();

    // Second request - should get fresh data
    $response2 = $this->getJson("/admin/insurance/plans/{$plan->id}/coverage/drug/exceptions");
    $data2 = $response2->json('exceptions');

    expect(count($data2))->toBeGreaterThan(count($data1));
})->group('performance');

it('caches report data for 5 minutes', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
    InsuranceClaim::factory()->count(10)->create([
        'patient_insurance_id' => \App\Models\PatientInsurance::factory()->create([
            'insurance_plan_id' => $plan->id,
        ])->id,
        'date_of_attendance' => '2025-01-15',
    ]);

    $this->actingAs($this->user);

    // Clear cache
    Cache::tags(['insurance-reports', 'claims-summary'])->flush();

    // First request
    $response1 = $this->getJson('/admin/insurance/reports/claims-summary?date_from=2025-01-01&date_to=2025-01-31');
    $data1 = $response1->json('data');

    // Add more claims
    InsuranceClaim::factory()->count(5)->create([
        'patient_insurance_id' => \App\Models\PatientInsurance::factory()->create([
            'insurance_plan_id' => $plan->id,
        ])->id,
        'date_of_attendance' => '2025-01-20',
    ]);

    // Second request within 5 minutes - should return cached data
    $response2 = $this->getJson('/admin/insurance/reports/claims-summary?date_from=2025-01-01&date_to=2025-01-31');
    $data2 = $response2->json('data');

    // Data should be the same (cached)
    expect($data2['total_claims'])->toBe($data1['total_claims']);

    // Clear cache
    Cache::tags(['insurance-reports', 'claims-summary'])->flush();

    // Third request after cache clear - should return fresh data
    $response3 = $this->getJson('/admin/insurance/reports/claims-summary?date_from=2025-01-01&date_to=2025-01-31');
    $data3 = $response3->json('data');

    // Data should be updated
    expect($data3['total_claims'])->toBeGreaterThan($data1['total_claims']);
})->group('performance');

it('caches coverage exceptions per plan and category', function () {
    $provider = InsuranceProvider::factory()->create();
    $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);

    // Create exceptions
    for ($i = 0; $i < 10; $i++) {
        $plan->coverageRules()->create([
            'coverage_category' => 'drug',
            'item_code' => "DRUG-{$i}",
            'item_description' => "Drug {$i}",
            'coverage_type' => 'percentage',
            'coverage_value' => 90.00,
            'patient_copay_percentage' => 10.00,
            'is_covered' => true,
            'is_active' => true,
        ]);
    }

    $this->actingAs($this->user);

    // Clear cache
    Cache::tags(['insurance-plans', "plan-{$plan->id}", 'category-drug'])->flush();

    // First request - populate cache
    $startTime1 = microtime(true);
    $response1 = $this->getJson("/admin/insurance/plans/{$plan->id}/coverage/drug/exceptions");
    $time1 = microtime(true) - $startTime1;

    // Second request - should use cache (faster)
    $startTime2 = microtime(true);
    $response2 = $this->getJson("/admin/insurance/plans/{$plan->id}/coverage/drug/exceptions");
    $time2 = microtime(true) - $startTime2;

    $response1->assertSuccessful();
    $response2->assertSuccessful();

    // Cached request should be faster
    expect($time2)->toBeLessThan($time1);
})->group('performance');
