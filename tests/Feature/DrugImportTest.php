<?php

use App\Imports\DrugImport;
use App\Models\Drug;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create the permission if it doesn't exist
    Permission::findOrCreate('drugs.create', 'web');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('drugs.create');
});

it('imports drugs from CSV data', function () {
    $rows = [
        [
            'drug_code' => 'DRG001',
            'name' => 'Test Drug 1',
            'unit_price' => '10.50',
            'category' => 'Antibiotic',
        ],
        [
            'drug_code' => 'DRG002',
            'name' => 'Test Drug 2',
            'unit_price' => '25.00',
            'category' => 'Analgesic',
        ],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(2);
    expect($results['updated'])->toBe(0);
    expect($results['errors'])->toBeEmpty();

    expect(Drug::count())->toBe(2);
    expect(Drug::where('drug_code', 'DRG001')->first()->name)->toBe('Test Drug 1');
});

it('updates existing drugs on import', function () {
    Drug::factory()->create([
        'drug_code' => 'DRG001',
        'name' => 'Old Name',
        'unit_price' => 5.00,
    ]);

    $rows = [
        [
            'drug_code' => 'DRG001',
            'name' => 'New Name',
            'unit_price' => '15.00',
        ],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(0);
    expect($results['updated'])->toBe(1);

    $drug = Drug::where('drug_code', 'DRG001')->first();
    expect($drug->name)->toBe('New Name');
    expect((float) $drug->unit_price)->toBe(15.00);
});

it('auto-creates NHIS mapping when nhis_code provided', function () {
    $nhisTariff = NhisTariff::factory()->create([
        'nhis_code' => 'AMOXICCA1',
        'name' => 'Amoxicillin Capsule 250mg',
        'price' => 0.47,
    ]);

    $rows = [
        [
            'drug_code' => 'DRG001',
            'name' => 'Amoxicillin 250mg Caps',
            'unit_price' => '2.50',
            'nhis_code' => 'AMOXICCA1',
        ],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(1);
    expect($results['mapped'])->toBe(1);

    $drug = Drug::where('drug_code', 'DRG001')->first();
    $mapping = NhisItemMapping::where('item_type', 'drug')
        ->where('item_id', $drug->id)
        ->first();

    expect($mapping)->not->toBeNull();
    expect($mapping->nhis_tariff_id)->toBe($nhisTariff->id);
});

it('creates drug without mapping when nhis_code not found', function () {
    $rows = [
        [
            'drug_code' => 'DRG001',
            'name' => 'Test Drug',
            'unit_price' => '10.00',
            'nhis_code' => 'INVALID_CODE',
        ],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(1);
    expect($results['mapped'])->toBe(0);
    expect($results['errors'])->toHaveCount(1);
    expect($results['errors'][0]['error'])->toContain('INVALID_CODE');

    expect(Drug::count())->toBe(1);
    expect(NhisItemMapping::count())->toBe(0);
});

it('skips rows with missing required fields', function () {
    // Only drug_code and name are required - unit_price defaults to 0
    $rows = [
        ['drug_code' => '', 'name' => 'No Code', 'unit_price' => '10.00'],
        ['drug_code' => 'DRG001', 'name' => '', 'unit_price' => '10.00'],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(0);
    expect($results['skipped'])->toBe(2);
    expect($results['errors'])->toHaveCount(2);
});

it('creates drug with zero price when unit_price is empty', function () {
    $rows = [
        ['drug_code' => 'DRG002', 'name' => 'No Price Drug', 'unit_price' => ''],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(1);
    expect(Drug::where('drug_code', 'DRG002')->first()->unit_price)->toBe('0.00');
});

it('can download import template', function () {
    // Create required permissions for pharmacy routes
    Permission::findOrCreate('pharmacy.view', 'web');
    $this->user->givePermissionTo(['drugs.create', 'pharmacy.view']);

    $response = $this->actingAs($this->user)
        ->get('/pharmacy/drugs-import/template');

    $response->assertOk();
    $response->assertDownload('drug_import_template.xlsx');
});

it('imports bottle_size for bottles and vials', function () {
    $rows = [
        [
            'drug_code' => 'SYR001',
            'name' => 'Ibuprofen Suspension 100mg/5ml',
            'unit_price' => '15.00',
            'unit_type' => 'bottle',
            'bottle_size' => '100',
        ],
        [
            'drug_code' => 'INJ001',
            'name' => 'Gentamicin Injection 80mg/2ml',
            'unit_price' => '8.00',
            'unit_type' => 'vial',
            'bottle_size' => '2',
        ],
    ];

    $importer = new DrugImport;
    $results = $importer->processRows($rows);

    expect($results['created'])->toBe(2);

    $syrup = Drug::where('drug_code', 'SYR001')->first();
    expect($syrup->bottle_size)->toBe(100);
    expect($syrup->unit_type)->toBe('bottle');

    $injection = Drug::where('drug_code', 'INJ001')->first();
    expect($injection->bottle_size)->toBe(2);
    expect($injection->unit_type)->toBe('vial');
});
