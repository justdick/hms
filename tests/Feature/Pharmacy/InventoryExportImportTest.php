<?php

use App\Imports\InventoryImport;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can import inventory from exported data', function () {
    // Create drug first (required for import)
    $drug = Drug::factory()->create(['drug_code' => 'DRG001']);
    Supplier::factory()->create(['name' => 'Supplier A']);

    $rows = [
        [
            'drug_code' => 'DRG001',
            'drug_name' => $drug->name,
            'batch_number' => 'BATCH-123',
            'supplier_name' => 'Supplier A',
            'quantity_received' => 100,
            'quantity_remaining' => 75,
            'cost_per_unit' => '5.00',
            'selling_price_per_unit' => '10.00',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'manufacture_date' => now()->subMonth()->format('Y-m-d'),
            'received_date' => now()->format('Y-m-d'),
            'notes' => 'Test batch',
        ],
    ];

    $importer = new InventoryImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(1);
    expect($results['errors'])->toBeEmpty();
    expect(DrugBatch::where('batch_number', 'BATCH-123')->exists())->toBeTrue();
});

test('import updates existing batch', function () {
    $drug = Drug::factory()->create(['drug_code' => 'DRG002']);
    $supplier = Supplier::factory()->create(['name' => 'Original Supplier']);
    $batch = DrugBatch::factory()->create([
        'drug_id' => $drug->id,
        'supplier_id' => $supplier->id,
        'batch_number' => 'EXISTING-001',
        'quantity_remaining' => 50,
        'expiry_date' => now()->addYear(),
    ]);

    $rows = [
        [
            'drug_code' => 'DRG002',
            'drug_name' => $drug->name,
            'batch_number' => 'EXISTING-001',
            'supplier_name' => 'Original Supplier',
            'quantity_received' => '100',
            'quantity_remaining' => '80',
            'cost_per_unit' => '5.00',
            'selling_price_per_unit' => '10.00',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'manufacture_date' => '',
            'received_date' => now()->format('Y-m-d'),
            'notes' => '',
        ],
    ];

    $importer = new InventoryImport;
    $results = $importer->processRows($rows);

    expect($results['updated'])->toBe(1)
        ->and($results['errors'])->toBeEmpty();
    expect($batch->fresh()->quantity_remaining)->toBe(80);
});

test('import fails for non-existent drug', function () {
    $rows = [
        [
            'drug_code' => 'NONEXISTENT',
            'drug_name' => 'Some Drug',
            'batch_number' => 'BATCH-001',
            'supplier_name' => '',
            'quantity_received' => 100,
            'quantity_remaining' => 100,
            'cost_per_unit' => '5.00',
            'selling_price_per_unit' => '10.00',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'manufacture_date' => '',
            'received_date' => '',
            'notes' => '',
        ],
    ];

    $importer = new InventoryImport;
    $results = $importer->processRows($rows);

    expect($results['skipped'])->toBe(1);
    expect($results['errors'])->not->toBeEmpty();
});

test('import creates supplier if not exists', function () {
    $drug = Drug::factory()->create(['drug_code' => 'DRG003']);

    $rows = [
        [
            'drug_code' => 'DRG003',
            'drug_name' => $drug->name,
            'batch_number' => 'BATCH-NEW',
            'supplier_name' => 'New Supplier Co',
            'quantity_received' => '100',
            'quantity_remaining' => '100',
            'cost_per_unit' => '5.00',
            'selling_price_per_unit' => '10.00',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'manufacture_date' => '',
            'received_date' => '',
            'notes' => '',
        ],
    ];

    $importer = new InventoryImport;
    $results = $importer->processRows($rows);

    // Debug
    if (! empty($results['errors'])) {
        dump($results['errors']);
    }

    expect($results['created'])->toBe(1)
        ->and($results['errors'])->toBeEmpty();
    expect(Supplier::where('name', 'New Supplier Co')->exists())->toBeTrue();
});
