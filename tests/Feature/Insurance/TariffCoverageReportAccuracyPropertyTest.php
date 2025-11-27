<?php

/**
 * Property-Based Test for Tariff Coverage Report Accuracy
 *
 * **Feature: nhis-claims-integration, Property 30: Tariff Coverage Report Accuracy**
 * **Validates: Requirements 18.4**
 *
 * Property: For any tariff coverage report, the percentage should accurately
 * reflect the count of mapped items divided by total items.
 */

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    NhisItemMapping::query()->delete();
    Drug::query()->delete();
    LabService::query()->delete();
    MinorProcedureType::query()->delete();
    NhisTariff::query()->delete();
    Permission::firstOrCreate(['name' => 'insurance.view-reports']);
});

/**
 * Generate random coverage scenarios for property testing
 */
dataset('random_coverage_scenarios', function () {
    return [
        [10, 5, 8, 4, 6, 3],
        [5, 5, 3, 3, 2, 2],
        [10, 0, 5, 0, 3, 0],
        [0, 0, 0, 0, 0, 0],
        [20, 15, 10, 7, 5, 2],
    ];
});

it('accurately calculates coverage percentage for each item type', function (
    int $totalDrugs,
    int $mappedDrugs,
    int $totalLabs,
    int $mappedLabs,
    int $totalProcedures,
    int $mappedProcedures
) {
    // Arrange: Create drugs
    $drugs = Drug::factory()->count($totalDrugs)->create(['is_active' => true]);

    // Create lab services
    $labs = LabService::factory()->count($totalLabs)->create(['is_active' => true]);

    // Create procedures
    $procedures = MinorProcedureType::factory()->count($totalProcedures)->create(['is_active' => true]);

    // Create NHIS tariffs and mappings for drugs
    for ($i = 0; $i < $mappedDrugs && $i < $totalDrugs; $i++) {
        $tariff = NhisTariff::factory()->medicine()->create();
        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drugs[$i]->id,
            'item_code' => $drugs[$i]->drug_code,
            'nhis_tariff_id' => $tariff->id,
        ]);
    }

    // Create NHIS tariffs and mappings for labs
    for ($i = 0; $i < $mappedLabs && $i < $totalLabs; $i++) {
        $tariff = NhisTariff::factory()->lab()->create();
        NhisItemMapping::create([
            'item_type' => 'lab_service',
            'item_id' => $labs[$i]->id,
            'item_code' => $labs[$i]->code,
            'nhis_tariff_id' => $tariff->id,
        ]);
    }

    // Create NHIS tariffs and mappings for procedures
    for ($i = 0; $i < $mappedProcedures && $i < $totalProcedures; $i++) {
        $tariff = NhisTariff::factory()->procedure()->create();
        NhisItemMapping::create([
            'item_type' => 'procedure',
            'item_id' => $procedures[$i]->id,
            'item_code' => $procedures[$i]->code,
            'nhis_tariff_id' => $tariff->id,
        ]);
    }

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.tariff-coverage'));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Drug coverage
    if ($totalDrugs > 0) {
        $expectedDrugPercentage = round(($mappedDrugs / $totalDrugs) * 100, 2);
        expect($data['coverage_by_type']['drugs']['total'])->toBe($totalDrugs)
            ->and($data['coverage_by_type']['drugs']['mapped'])->toBe($mappedDrugs)
            ->and($data['coverage_by_type']['drugs']['unmapped'])->toBe($totalDrugs - $mappedDrugs)
            ->and((float) $data['coverage_by_type']['drugs']['percentage'])->toEqual($expectedDrugPercentage);
    }

    // Assert: Lab coverage
    if ($totalLabs > 0) {
        $expectedLabPercentage = round(($mappedLabs / $totalLabs) * 100, 2);
        expect($data['coverage_by_type']['lab_services']['total'])->toBe($totalLabs)
            ->and($data['coverage_by_type']['lab_services']['mapped'])->toBe($mappedLabs)
            ->and($data['coverage_by_type']['lab_services']['unmapped'])->toBe($totalLabs - $mappedLabs)
            ->and((float) $data['coverage_by_type']['lab_services']['percentage'])->toEqual($expectedLabPercentage);
    }

    // Assert: Procedure coverage
    if ($totalProcedures > 0) {
        $expectedProcedurePercentage = round(($mappedProcedures / $totalProcedures) * 100, 2);
        expect($data['coverage_by_type']['procedures']['total'])->toBe($totalProcedures)
            ->and($data['coverage_by_type']['procedures']['mapped'])->toBe($mappedProcedures)
            ->and($data['coverage_by_type']['procedures']['unmapped'])->toBe($totalProcedures - $mappedProcedures)
            ->and((float) $data['coverage_by_type']['procedures']['percentage'])->toEqual($expectedProcedurePercentage);
    }

    // Assert: Overall coverage
    $totalItems = $totalDrugs + $totalLabs + $totalProcedures;
    $totalMapped = $mappedDrugs + $mappedLabs + $mappedProcedures;
    $expectedOverallPercentage = $totalItems > 0 ? round(($totalMapped / $totalItems) * 100, 2) : 0;

    expect($data['overall']['total'])->toBe($totalItems)
        ->and($data['overall']['mapped'])->toBe($totalMapped)
        ->and($data['overall']['unmapped'])->toBe($totalItems - $totalMapped)
        ->and((float) $data['overall']['percentage'])->toEqual($expectedOverallPercentage);
})->with('random_coverage_scenarios');

it('only counts active items in coverage calculation', function () {
    // Arrange: Create active and inactive drugs
    $activeDrugs = Drug::factory()->count(5)->create(['is_active' => true]);
    Drug::factory()->count(3)->create(['is_active' => false]); // Inactive drugs

    // Create active and inactive lab services
    $activeLabs = LabService::factory()->count(4)->create(['is_active' => true]);
    LabService::factory()->count(2)->create(['is_active' => false]); // Inactive labs

    // Map some active items
    $tariff1 = NhisTariff::factory()->medicine()->create();
    NhisItemMapping::create([
        'item_type' => 'drug',
        'item_id' => $activeDrugs[0]->id,
        'item_code' => $activeDrugs[0]->drug_code,
        'nhis_tariff_id' => $tariff1->id,
    ]);

    $tariff2 = NhisTariff::factory()->lab()->create();
    NhisItemMapping::create([
        'item_type' => 'lab_service',
        'item_id' => $activeLabs[0]->id,
        'item_code' => $activeLabs[0]->code,
        'nhis_tariff_id' => $tariff2->id,
    ]);

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.tariff-coverage'));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: Only active items are counted
    expect($data['coverage_by_type']['drugs']['total'])->toBe(5) // Only active drugs
        ->and($data['coverage_by_type']['lab_services']['total'])->toBe(4); // Only active labs
});

it('handles zero items gracefully', function () {
    // Arrange: No items created

    // Act
    $user = User::factory()->create();
    $user->givePermissionTo('insurance.view-reports');

    $response = $this->actingAs($user)
        ->getJson(route('admin.insurance.reports.tariff-coverage'));

    $response->assertOk();
    $data = $response->json('data');

    // Assert: All zeros, no division by zero errors
    expect($data['overall']['total'])->toBe(0)
        ->and($data['overall']['mapped'])->toBe(0)
        ->and((float) $data['overall']['percentage'])->toEqual(0.0);
});
