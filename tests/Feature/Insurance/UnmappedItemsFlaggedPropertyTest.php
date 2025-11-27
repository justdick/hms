<?php

/**
 * Property-Based Test for Unmapped Items Flagged
 *
 * **Feature: nhis-claims-integration, Property 4: Unmapped Items Flagged**
 * **Validates: Requirements 2.6, 5.4, 12.4**
 *
 * Property: For any item that is not mapped to an NHIS tariff, the system should
 * mark it as "NHIS Not Covered" during claim generation and exclude it from the
 * NHIS claim total.
 */

use App\Models\Drug;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Services\NhisTariffService;

beforeEach(function () {
    // Clean up existing data
    NhisItemMapping::query()->delete();
    NhisTariff::query()->delete();
    // Note: We don't delete Drug, LabService, MinorProcedureType as they may have
    // foreign key constraints. The tests should work with existing data.
});

dataset('item_types', function () {
    return [
        ['drug'],
        ['lab_service'],
        ['procedure'],
    ];
});

dataset('item_counts', function () {
    return [
        [3],
        [5],
        [10],
    ];
});

it('correctly identifies unmapped items as not having NHIS mapping', function (string $itemType, int $count) {
    // Arrange
    $service = new NhisTariffService;

    // Create items based on type
    $items = match ($itemType) {
        'drug' => Drug::factory()->count($count)->create(),
        'lab_service' => LabService::factory()->count($count)->create(),
        'procedure' => MinorProcedureType::factory()->count($count)->create(),
    };

    // Act & Assert: All items should be flagged as unmapped
    foreach ($items as $item) {
        $isMapped = $service->isItemMapped($itemType, $item->id);
        expect($isMapped)->toBeFalse("Item {$item->id} of type {$itemType} should be flagged as unmapped");
    }
})->with('item_types')->with('item_counts');

it('correctly identifies mapped items as having NHIS mapping', function (string $itemType, int $count) {
    // Arrange
    $service = new NhisTariffService;

    // Create items and map them
    $items = match ($itemType) {
        'drug' => Drug::factory()->count($count)->create(),
        'lab_service' => LabService::factory()->count($count)->create(),
        'procedure' => MinorProcedureType::factory()->count($count)->create(),
    };

    // Create NHIS tariffs and mappings for each item
    foreach ($items as $item) {
        $tariffCategory = match ($itemType) {
            'drug' => 'medicine',
            'lab_service' => 'lab',
            'procedure' => 'procedure',
        };

        $tariff = NhisTariff::factory()->create(['category' => $tariffCategory]);

        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        NhisItemMapping::create([
            'item_type' => $itemType,
            'item_id' => $item->id,
            'item_code' => $item->{$codeField},
            'nhis_tariff_id' => $tariff->id,
        ]);
    }

    // Act & Assert: All items should be identified as mapped
    foreach ($items as $item) {
        $isMapped = $service->isItemMapped($itemType, $item->id);
        expect($isMapped)->toBeTrue("Item {$item->id} of type {$itemType} should be identified as mapped");
    }
})->with('item_types')->with('item_counts');

it('returns null tariff price for unmapped items', function (string $itemType) {
    // Arrange
    $service = new NhisTariffService;

    // Create an item without mapping
    $item = match ($itemType) {
        'drug' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    // Act
    $tariffPrice = $service->getTariffPrice($itemType, $item->id);

    // Assert: Should return null for unmapped items
    expect($tariffPrice)->toBeNull("Unmapped {$itemType} should return null tariff price");
})->with('item_types');

it('returns correct tariff price for mapped items', function (string $itemType) {
    // Arrange
    $service = new NhisTariffService;
    $expectedPrice = fake()->randomFloat(2, 10, 500);

    // Create an item
    $item = match ($itemType) {
        'drug' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    // Create tariff with known price
    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
    };

    $tariff = NhisTariff::factory()->create([
        'category' => $tariffCategory,
        'price' => $expectedPrice,
    ]);

    // Create mapping
    $codeField = NhisItemMapping::getCodeFieldForType($itemType);
    NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $item->{$codeField},
        'nhis_tariff_id' => $tariff->id,
    ]);

    // Act
    $tariffPrice = $service->getTariffPrice($itemType, $item->id);

    // Assert: Should return the correct tariff price
    expect(round($tariffPrice, 2))->toBe(round($expectedPrice, 2));
})->with('item_types');

it('distinguishes between mapped and unmapped items in mixed set', function (string $itemType) {
    // Arrange
    $service = new NhisTariffService;

    // Create items - some will be mapped, some won't
    $mappedItems = match ($itemType) {
        'drug' => Drug::factory()->count(3)->create(),
        'lab_service' => LabService::factory()->count(3)->create(),
        'procedure' => MinorProcedureType::factory()->count(3)->create(),
    };

    $unmappedItems = match ($itemType) {
        'drug' => Drug::factory()->count(3)->create(),
        'lab_service' => LabService::factory()->count(3)->create(),
        'procedure' => MinorProcedureType::factory()->count(3)->create(),
    };

    // Create mappings only for mapped items
    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
    };

    foreach ($mappedItems as $item) {
        $tariff = NhisTariff::factory()->create(['category' => $tariffCategory]);
        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        NhisItemMapping::create([
            'item_type' => $itemType,
            'item_id' => $item->id,
            'item_code' => $item->{$codeField},
            'nhis_tariff_id' => $tariff->id,
        ]);
    }

    // Act & Assert: Mapped items should be identified as mapped
    foreach ($mappedItems as $item) {
        expect($service->isItemMapped($itemType, $item->id))->toBeTrue();
        expect($service->getTariffPrice($itemType, $item->id))->not->toBeNull();
        expect($service->getTariffForItem($itemType, $item->id))->not->toBeNull();
    }

    // Act & Assert: Unmapped items should be flagged as unmapped
    foreach ($unmappedItems as $item) {
        expect($service->isItemMapped($itemType, $item->id))->toBeFalse();
        expect($service->getTariffPrice($itemType, $item->id))->toBeNull();
        expect($service->getTariffForItem($itemType, $item->id))->toBeNull();
    }
})->with('item_types');

it('treats items with inactive tariff mappings as unmapped', function (string $itemType) {
    // Arrange
    $service = new NhisTariffService;

    // Create an item
    $item = match ($itemType) {
        'drug' => Drug::factory()->create(),
        'lab_service' => LabService::factory()->create(),
        'procedure' => MinorProcedureType::factory()->create(),
    };

    // Create INACTIVE tariff
    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
    };

    $tariff = NhisTariff::factory()->inactive()->create(['category' => $tariffCategory]);

    // Create mapping to inactive tariff
    $codeField = NhisItemMapping::getCodeFieldForType($itemType);
    NhisItemMapping::create([
        'item_type' => $itemType,
        'item_id' => $item->id,
        'item_code' => $item->{$codeField},
        'nhis_tariff_id' => $tariff->id,
    ]);

    // Act & Assert: Item should be treated as unmapped because tariff is inactive
    expect($service->isItemMapped($itemType, $item->id))->toBeFalse();
    expect($service->getTariffPrice($itemType, $item->id))->toBeNull();
    expect($service->getTariffForItem($itemType, $item->id))->toBeNull();
})->with('item_types');

it('returns correct unmapped items list', function (string $itemType) {
    // Arrange
    $service = new NhisTariffService;

    // Create items - some will be mapped, some won't
    // Ensure all items are active so they appear in the unmapped list
    $mappedItems = match ($itemType) {
        'drug' => Drug::factory()->count(2)->create(['is_active' => true]),
        'lab_service' => LabService::factory()->count(2)->active()->create(),
        'procedure' => MinorProcedureType::factory()->count(2)->create(['is_active' => true]),
    };

    $unmappedItems = match ($itemType) {
        'drug' => Drug::factory()->count(3)->create(['is_active' => true]),
        'lab_service' => LabService::factory()->count(3)->active()->create(),
        'procedure' => MinorProcedureType::factory()->count(3)->create(['is_active' => true]),
    };

    // Create mappings only for mapped items
    $tariffCategory = match ($itemType) {
        'drug' => 'medicine',
        'lab_service' => 'lab',
        'procedure' => 'procedure',
    };

    foreach ($mappedItems as $item) {
        $tariff = NhisTariff::factory()->create(['category' => $tariffCategory]);
        $codeField = NhisItemMapping::getCodeFieldForType($itemType);

        NhisItemMapping::create([
            'item_type' => $itemType,
            'item_id' => $item->id,
            'item_code' => $item->{$codeField},
            'nhis_tariff_id' => $tariff->id,
        ]);
    }

    // Act
    $unmappedResult = $service->getUnmappedItems($itemType);
    $unmappedResultIds = $unmappedResult->pluck('id')->toArray();

    // Assert: All our unmapped items should be in the result
    $unmappedIds = $unmappedItems->pluck('id')->toArray();
    foreach ($unmappedIds as $unmappedId) {
        expect(in_array($unmappedId, $unmappedResultIds))->toBeTrue(
            "Unmapped item {$unmappedId} should be in the unmapped list"
        );
    }

    // Assert: Mapped items should NOT be in the result
    $mappedIds = $mappedItems->pluck('id')->toArray();
    foreach ($mappedIds as $mappedId) {
        expect(in_array($mappedId, $unmappedResultIds))->toBeFalse(
            "Mapped item {$mappedId} should NOT be in the unmapped list"
        );
    }

    // Assert: Result should contain at least our unmapped items
    expect($unmappedResult->count())->toBeGreaterThanOrEqual(3);
})->with('item_types');
