<?php

use App\Exports\CoverageExceptionTemplate;
use App\Models\BillingService;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\LabService;
use App\Models\User;

use function Pest\Laravel\actingAs;

uses()->group('exports');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plan = InsurancePlan::factory()->create();
});

it('template includes correct items for drug category', function () {
    actingAs($this->user);

    // Create test drugs
    Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Aspirin',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    Drug::factory()->create([
        'drug_code' => 'DRUG002',
        'name' => 'Paracetamol',
        'unit_price' => 5.00,
        'is_active' => true,
    ]);

    // Create inactive drug (should not be included)
    Drug::factory()->create([
        'drug_code' => 'DRUG003',
        'name' => 'Inactive Drug',
        'unit_price' => 15.00,
        'is_active' => false,
    ]);

    $template = new CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1]; // PrePopulatedDataSheet is second sheet
    $collection = $dataSheet->collection();

    expect($collection)->toHaveCount(2);
    expect($collection->pluck('item_code')->toArray())->toContain('DRUG001', 'DRUG002');
    expect($collection->pluck('item_code')->toArray())->not->toContain('DRUG003');
});

it('template includes correct items for lab category', function () {
    actingAs($this->user);

    LabService::factory()->create([
        'code' => 'LAB001',
        'name' => 'Blood Test',
        'price' => 50.00,
        'is_active' => true,
    ]);

    LabService::factory()->create([
        'code' => 'LAB002',
        'name' => 'Urine Test',
        'price' => 30.00,
        'is_active' => true,
    ]);

    $template = new CoverageExceptionTemplate('lab', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    expect($collection)->toHaveCount(2);
    expect($collection->pluck('item_code')->toArray())->toContain('LAB001', 'LAB002');
});

it('template includes correct items for consultation category', function () {
    actingAs($this->user);

    BillingService::factory()->create([
        'service_code' => 'CONS001',
        'service_name' => 'General Consultation',
        'base_price' => 100.00,
        'service_type' => 'consultation',
        'is_active' => true,
    ]);

    BillingService::factory()->create([
        'service_code' => 'CONS002',
        'service_name' => 'Specialist Consultation',
        'base_price' => 200.00,
        'service_type' => 'consultation',
        'is_active' => true,
    ]);

    $template = new CoverageExceptionTemplate('consultation', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    expect($collection)->toHaveCount(2);
    expect($collection->pluck('item_code')->toArray())->toContain('CONS001', 'CONS002');
});

it('template pre-fills existing specific rule values', function () {
    actingAs($this->user);

    Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Aspirin',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    // Create existing specific rule - use 'fixed' not 'fixed_amount'
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'DRUG001',
        'coverage_type' => 'fixed',
        'coverage_value' => 30,
        'notes' => 'Special coverage',
    ]);

    $template = new CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    $item = $collection->firstWhere('item_code', 'DRUG001');
    expect($item['coverage_type'])->toBe('fixed_amount'); // Template maps 'fixed' to 'fixed_amount'
    expect((float) $item['coverage_value'])->toBe(30.0);
    expect($item['notes'])->toBe('Special coverage');
});

it('template falls back to general rule values when no specific rule exists', function () {
    actingAs($this->user);

    Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Aspirin',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    // Create general rule (no item_code)
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => null,
        'coverage_type' => 'percentage',
        'coverage_value' => 90,
    ]);

    $template = new CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    $item = $collection->firstWhere('item_code', 'DRUG001');
    expect($item['coverage_type'])->toBe('percentage');
    expect((float) $item['coverage_value'])->toBe(90.0);
});

it('template uses default values when no rules exist', function () {
    actingAs($this->user);

    Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Aspirin',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    $template = new CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    $item = $collection->firstWhere('item_code', 'DRUG001');
    expect($item['coverage_type'])->toBe('percentage');
    expect($item['coverage_value'])->toBe(80);
    expect($item['notes'])->toBe('');
});

it('template sorts items alphabetically by name', function () {
    actingAs($this->user);

    Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Zinc Supplement',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    Drug::factory()->create([
        'drug_code' => 'DRUG002',
        'name' => 'Aspirin',
        'unit_price' => 5.00,
        'is_active' => true,
    ]);

    Drug::factory()->create([
        'drug_code' => 'DRUG003',
        'name' => 'Metformin',
        'unit_price' => 15.00,
        'is_active' => true,
    ]);

    $template = new CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    $names = $collection->pluck('item_name')->toArray();
    expect($names)->toBe(['Aspirin', 'Metformin', 'Zinc Supplement']);
});

it('template includes current price', function () {
    actingAs($this->user);

    Drug::factory()->create([
        'drug_code' => 'DRUG001',
        'name' => 'Aspirin',
        'unit_price' => 12.50,
        'is_active' => true,
    ]);

    $template = new CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    $item = $collection->firstWhere('item_code', 'DRUG001');
    expect($item['current_price'])->toBe('12.50');
});
