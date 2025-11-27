<?php

/**
 * Property-Based Test for Unmapped Items Filter
 *
 * **Feature: nhis-claims-integration, Property 6: Unmapped Items Filter**
 * **Validates: Requirements 2.3**
 *
 * Property: For any search for unmapped items, all returned results should have
 * no NHIS mapping associated with them.
 */

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Clean up existing data
    NhisItemMapping::query()->delete();
    Drug::query()->delete();
    LabService::query()->delete();
    MinorProcedureType::query()->delete();
    NhisTariff::query()->delete();

    // Create permissions
    Permission::firstOrCreate(['name' => 'nhis-mappings.view']);
    Permission::firstOrCreate(['name' => 'nhis-mappings.manage']);
});

dataset('item_counts', function () {
    return [
        'small set' => [3, 2],
        'medium set' => [5, 3],
        'larger set' => [10, 5],
    ];
});

dataset('item_types', function () {
    return [
        'drugs' => ['drug'],
        'lab services' => ['lab_service'],
        'procedures' => ['procedure'],
    ];
});

it('returns only unmapped items for drugs', function (int $totalItems, int $mappedCount) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.view');

    // Create drugs
    $drugs = Drug::factory()->count($totalItems)->create();

    // Map some of them
    $nhisTariff = NhisTariff::factory()->medicine()->create();
    $mappedDrugs = $drugs->take($mappedCount);

    foreach ($mappedDrugs as $drug) {
        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/admin/nhis-mappings/unmapped?item_type=drug');

    // Assert
    $response->assertOk();

    $returnedItems = $response->json('items');
    $expectedUnmappedCount = $totalItems - $mappedCount;

    expect(count($returnedItems))->toBe($expectedUnmappedCount);

    // Property: All returned items should have no NHIS mapping
    foreach ($returnedItems as $item) {
        $mappingExists = NhisItemMapping::where('item_type', 'drug')
            ->where('item_id', $item['id'])
            ->exists();

        expect($mappingExists)->toBeFalse(
            "Item with ID {$item['id']} should not have an NHIS mapping but one was found"
        );
    }
})->with('item_counts');

it('returns only unmapped items for lab services', function (int $totalItems, int $mappedCount) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.view');

    // Create lab services
    $labServices = LabService::factory()->count($totalItems)->create();

    // Map some of them
    $nhisTariff = NhisTariff::factory()->lab()->create();
    $mappedServices = $labServices->take($mappedCount);

    foreach ($mappedServices as $service) {
        NhisItemMapping::create([
            'item_type' => 'lab_service',
            'item_id' => $service->id,
            'item_code' => $service->code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/admin/nhis-mappings/unmapped?item_type=lab_service');

    // Assert
    $response->assertOk();

    $returnedItems = $response->json('items');
    $expectedUnmappedCount = $totalItems - $mappedCount;

    expect(count($returnedItems))->toBe($expectedUnmappedCount);

    // Property: All returned items should have no NHIS mapping
    foreach ($returnedItems as $item) {
        $mappingExists = NhisItemMapping::where('item_type', 'lab_service')
            ->where('item_id', $item['id'])
            ->exists();

        expect($mappingExists)->toBeFalse(
            "Lab service with ID {$item['id']} should not have an NHIS mapping but one was found"
        );
    }
})->with('item_counts');

it('returns only unmapped items for procedures', function (int $totalItems, int $mappedCount) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.view');

    // Create procedures
    $procedures = MinorProcedureType::factory()->count($totalItems)->create();

    // Map some of them
    $nhisTariff = NhisTariff::factory()->procedure()->create();
    $mappedProcedures = $procedures->take($mappedCount);

    foreach ($mappedProcedures as $procedure) {
        NhisItemMapping::create([
            'item_type' => 'procedure',
            'item_id' => $procedure->id,
            'item_code' => $procedure->code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson('/admin/nhis-mappings/unmapped?item_type=procedure');

    // Assert
    $response->assertOk();

    $returnedItems = $response->json('items');
    $expectedUnmappedCount = $totalItems - $mappedCount;

    expect(count($returnedItems))->toBe($expectedUnmappedCount);

    // Property: All returned items should have no NHIS mapping
    foreach ($returnedItems as $item) {
        $mappingExists = NhisItemMapping::where('item_type', 'procedure')
            ->where('item_id', $item['id'])
            ->exists();

        expect($mappingExists)->toBeFalse(
            "Procedure with ID {$item['id']} should not have an NHIS mapping but one was found"
        );
    }
})->with('item_counts');

it('returns all items when none are mapped', function (string $itemType) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.view');

    $itemCount = 5;

    // Create items based on type
    match ($itemType) {
        'drug' => Drug::factory()->count($itemCount)->create(),
        'lab_service' => LabService::factory()->count($itemCount)->create(),
        'procedure' => MinorProcedureType::factory()->count($itemCount)->create(),
    };

    // Act
    $response = $this->actingAs($user)
        ->getJson("/admin/nhis-mappings/unmapped?item_type={$itemType}");

    // Assert
    $response->assertOk();

    $returnedItems = $response->json('items');
    expect(count($returnedItems))->toBe($itemCount);

    // Property: All returned items should have no NHIS mapping
    foreach ($returnedItems as $item) {
        $mappingExists = NhisItemMapping::where('item_type', $itemType)
            ->where('item_id', $item['id'])
            ->exists();

        expect($mappingExists)->toBeFalse();
    }
})->with('item_types');

it('returns empty list when all items are mapped', function (string $itemType) {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.view');

    $itemCount = 3;

    // Create items and map all of them
    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
    };

    $nhisTariff = NhisTariff::factory()->create(['category' => $tariffCategory]);

    $items = match ($itemType) {
        'drug' => Drug::factory()->count($itemCount)->create(),
        'lab_service' => LabService::factory()->count($itemCount)->create(),
        'procedure' => MinorProcedureType::factory()->count($itemCount)->create(),
    };

    $codeField = NhisItemMapping::getCodeFieldForType($itemType);

    foreach ($items as $item) {
        NhisItemMapping::create([
            'item_type' => $itemType,
            'item_id' => $item->id,
            'item_code' => $item->{$codeField},
            'nhis_tariff_id' => $nhisTariff->id,
        ]);
    }

    // Act
    $response = $this->actingAs($user)
        ->getJson("/admin/nhis-mappings/unmapped?item_type={$itemType}");

    // Assert
    $response->assertOk();

    $returnedItems = $response->json('items');
    expect(count($returnedItems))->toBe(0);
})->with('item_types');

it('filters unmapped items by search term', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.view');

    // Create drugs with specific names
    Drug::factory()->create(['name' => 'Paracetamol 500mg', 'drug_code' => 'DRG-001']);
    Drug::factory()->create(['name' => 'Amoxicillin 250mg', 'drug_code' => 'DRG-002']);
    Drug::factory()->create(['name' => 'Paracetamol 1000mg', 'drug_code' => 'DRG-003']);

    // Act
    $response = $this->actingAs($user)
        ->getJson('/admin/nhis-mappings/unmapped?item_type=drug&search=Paracetamol');

    // Assert
    $response->assertOk();

    $returnedItems = $response->json('items');
    expect(count($returnedItems))->toBe(2);

    // Property: All returned items should match search and have no mapping
    foreach ($returnedItems as $item) {
        expect(str_contains(strtolower($item['name']), 'paracetamol'))->toBeTrue();

        $mappingExists = NhisItemMapping::where('item_type', 'drug')
            ->where('item_id', $item['id'])
            ->exists();

        expect($mappingExists)->toBeFalse();
    }
});

it('excludes newly mapped items from unmapped list', function () {
    // Arrange
    $user = User::factory()->create();
    $user->givePermissionTo('nhis-mappings.manage');

    // Create drugs
    $drug1 = Drug::factory()->create();
    $drug2 = Drug::factory()->create();

    // Initially both should be unmapped
    $response1 = $this->actingAs($user)
        ->getJson('/admin/nhis-mappings/unmapped?item_type=drug');

    expect(count($response1->json('items')))->toBe(2);

    // Map one drug
    $nhisTariff = NhisTariff::factory()->medicine()->create();
    NhisItemMapping::create([
        'item_type' => 'drug',
        'item_id' => $drug1->id,
        'item_code' => $drug1->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Act: Check unmapped list again
    $response2 = $this->actingAs($user)
        ->getJson('/admin/nhis-mappings/unmapped?item_type=drug');

    // Assert
    $returnedItems = $response2->json('items');
    expect(count($returnedItems))->toBe(1);

    // Property: The mapped drug should not be in the list
    $returnedIds = array_column($returnedItems, 'id');
    expect(in_array($drug1->id, $returnedIds))->toBeFalse();
    expect(in_array($drug2->id, $returnedIds))->toBeTrue();
});
