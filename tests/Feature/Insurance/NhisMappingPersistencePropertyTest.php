<?php

/**
 * Property-Based Test for NHIS Mapping Persistence
 *
 * **Feature: nhis-claims-integration, Property 5: NHIS Mapping Persistence**
 * **Validates: Requirements 2.2**
 *
 * Property: For any item-to-NHIS mapping created, the link between the hospital
 * item and NHIS tariff code should be persisted and retrievable.
 */

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;

beforeEach(function () {
    // Clean up any existing mappings
    NhisItemMapping::query()->delete();
});

/**
 * Generate random item types for property testing
 */
dataset('item_types', function () {
    return [
        ['drug'],
        ['lab_service'],
        ['procedure'],
        ['consumable'],
    ];
});

/**
 * Generate random counts for property testing
 */
dataset('random_counts', function () {
    return [
        [1],
        [3],
        [5],
        [10],
    ];
});

it('persists mapping and allows retrieval by item type and id', function (string $itemType) {
    // Arrange: Create an item and NHIS tariff based on type
    $item = match ($itemType) {
        'drug', 'consumable' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
        'consumable' => 'consumable',
    };

    $nhisTariff = NhisTariff::factory()->create(['category' => $tariffCategory]);

    $codeField = NhisItemMapping::getCodeFieldForType($itemType);
    $itemCode = $item->{$codeField};

    // Act: Create the mapping
    $mapping = NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $itemCode,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Assert: Mapping is persisted
    expect($mapping->exists)->toBeTrue();
    expect($mapping->id)->toBeGreaterThan(0);

    // Assert: Mapping can be retrieved by item type and id
    $retrievedMapping = NhisItemMapping::forItem($itemType, $item->id)->first();

    expect($retrievedMapping)->not->toBeNull();
    expect($retrievedMapping->id)->toBe($mapping->id);
    expect($retrievedMapping->item_type)->toBe($itemType);
    expect($retrievedMapping->item_id)->toBe($item->id);
    expect($retrievedMapping->item_code)->toBe($itemCode);
    expect($retrievedMapping->nhis_tariff_id)->toBe($nhisTariff->id);
})->with('item_types');

it('persists mapping and allows retrieval by item code', function (string $itemType) {
    // Arrange: Create an item and NHIS tariff based on type
    $item = match ($itemType) {
        'drug', 'consumable' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
        'consumable' => 'consumable',
    };

    $nhisTariff = NhisTariff::factory()->create(['category' => $tariffCategory]);

    $codeField = NhisItemMapping::getCodeFieldForType($itemType);
    $itemCode = $item->{$codeField};

    // Act: Create the mapping
    $mapping = NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $itemCode,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Assert: Mapping can be retrieved by item code
    $retrievedMapping = NhisItemMapping::byItemCode($itemType, $itemCode)->first();

    expect($retrievedMapping)->not->toBeNull();
    expect($retrievedMapping->id)->toBe($mapping->id);
    expect($retrievedMapping->item_code)->toBe($itemCode);
})->with('item_types');

it('maintains relationship to NHIS tariff after persistence', function (string $itemType) {
    // Arrange: Create an item and NHIS tariff
    $item = match ($itemType) {
        'drug', 'consumable' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
        'consumable' => 'consumable',
    };

    $nhisTariff = NhisTariff::factory()->create([
        'category' => $tariffCategory,
        'price' => 150.00,
    ]);

    $codeField = NhisItemMapping::getCodeFieldForType($itemType);

    // Act: Create the mapping
    $mapping = NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $item->{$codeField},
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Assert: Relationship to NHIS tariff is maintained
    $freshMapping = NhisItemMapping::with('nhisTariff')->find($mapping->id);

    expect($freshMapping->nhisTariff)->not->toBeNull();
    expect($freshMapping->nhisTariff->id)->toBe($nhisTariff->id);
    expect($freshMapping->nhisTariff->nhis_code)->toBe($nhisTariff->nhis_code);
    expect($freshMapping->nhisTariff->price)->toBe('150.00');
})->with('item_types');

it('enforces unique constraint on item_type and item_id', function (string $itemType) {
    // Arrange: Create an item and two NHIS tariffs
    $item = match ($itemType) {
        'drug', 'consumable' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
        'consumable' => 'consumable',
    };

    $nhisTariff1 = NhisTariff::factory()->create(['category' => $tariffCategory]);
    $nhisTariff2 = NhisTariff::factory()->create(['category' => $tariffCategory]);

    $codeField = NhisItemMapping::getCodeFieldForType($itemType);

    // Act: Create first mapping
    NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $item->{$codeField},
        'nhis_tariff_id' => $nhisTariff1->id,
    ]);

    // Assert: Second mapping with same item_type and item_id should fail
    expect(fn () => NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $item->{$codeField},
        'nhis_tariff_id' => $nhisTariff2->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    // Assert: Only one mapping exists
    expect(NhisItemMapping::forItem($itemType, $item->id)->count())->toBe(1);
})->with('item_types');

it('allows same item_id with different item_types', function () {
    // Arrange: Create items with potentially same IDs but different types
    $drug = Drug::factory()->create();
    $labService = LabService::factory()->create();

    $medicineTariff = NhisTariff::factory()->medicine()->create();
    $labTariff = NhisTariff::factory()->lab()->create();

    // Act: Create mappings for different item types (even if item_id happens to be same)
    $drugMapping = NhisItemMapping::create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $medicineTariff->id,
    ]);

    $labMapping = NhisItemMapping::create([
        'item_type' => 'lab_service',
        'item_id' => $labService->id,
        'item_code' => $labService->code,
        'nhis_tariff_id' => $labTariff->id,
    ]);

    // Assert: Both mappings exist and are distinct
    expect($drugMapping->exists)->toBeTrue();
    expect($labMapping->exists)->toBeTrue();
    expect($drugMapping->id)->not->toBe($labMapping->id);

    // Assert: Each can be retrieved independently
    $retrievedDrug = NhisItemMapping::forItem('drug', $drug->id)->first();
    $retrievedLab = NhisItemMapping::forItem('lab_service', $labService->id)->first();

    expect($retrievedDrug->id)->toBe($drugMapping->id);
    expect($retrievedLab->id)->toBe($labMapping->id);
});

it('persists multiple mappings and retrieves them correctly', function (int $count) {
    // Arrange: Create multiple drugs and tariffs
    $drugs = Drug::factory()->count($count)->create();
    $tariffs = NhisTariff::factory()->medicine()->count($count)->create();

    // Act: Create mappings for each drug
    $mappings = [];
    foreach ($drugs as $index => $drug) {
        $mappings[] = NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $tariffs[$index]->id,
        ]);
    }

    // Assert: All mappings are persisted
    expect(NhisItemMapping::byItemType('drug')->count())->toBe($count);

    // Assert: Each mapping can be retrieved correctly
    foreach ($drugs as $index => $drug) {
        $retrieved = NhisItemMapping::forItem('drug', $drug->id)->first();
        expect($retrieved)->not->toBeNull();
        expect($retrieved->item_code)->toBe($drug->drug_code);
        expect($retrieved->nhis_tariff_id)->toBe($tariffs[$index]->id);
    }
})->with('random_counts');

it('retrieves NHIS tariff through mapping relationship', function () {
    // Arrange: Create a drug with specific NHIS tariff
    $drug = Drug::factory()->create();
    $nhisTariff = NhisTariff::factory()->medicine()->create([
        'nhis_code' => 'MED-TEST-001',
        'name' => 'Test Medicine Tariff',
        'price' => 25.50,
    ]);

    // Act: Create mapping
    $mapping = NhisItemMapping::create([
        'item_type' => 'drug',
        'item_id' => $drug->id,
        'item_code' => $drug->drug_code,
        'nhis_tariff_id' => $nhisTariff->id,
    ]);

    // Assert: Can access tariff details through relationship
    $freshMapping = NhisItemMapping::with('nhisTariff')->find($mapping->id);

    expect($freshMapping->nhisTariff->nhis_code)->toBe('MED-TEST-001');
    expect($freshMapping->nhisTariff->name)->toBe('Test Medicine Tariff');
    expect($freshMapping->nhisTariff->price)->toBe('25.50');
});

it('can access mappings from NHIS tariff side', function () {
    // Arrange: Create one tariff with multiple mappings
    $nhisTariff = NhisTariff::factory()->medicine()->create();

    $drugs = Drug::factory()->count(3)->create();

    foreach ($drugs as $drug) {
        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);
    }

    // Act: Load tariff with mappings
    $freshTariff = NhisTariff::with('itemMappings')->find($nhisTariff->id);

    // Assert: All mappings are accessible from tariff
    expect($freshTariff->itemMappings)->toHaveCount(3);
    expect($freshTariff->itemMappings->pluck('item_id')->toArray())
        ->toBe($drugs->pluck('id')->toArray());
});
