<?php

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

uses()->group('import', 'coverage');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plan = InsurancePlan::factory()->create();

    // Create test drugs
    Drug::factory()->create([
        'drug_code' => 'PARA500',
        'name' => 'Paracetamol 500mg',
        'unit_price' => 5.00,
        'is_active' => true,
    ]);

    Drug::factory()->create([
        'drug_code' => 'AMOX250',
        'name' => 'Amoxicillin 250mg',
        'unit_price' => 15.00,
        'is_active' => true,
    ]);

    Drug::factory()->create([
        'drug_code' => 'INSULIN',
        'name' => 'Insulin',
        'unit_price' => 50.00,
        'is_active' => true,
    ]);
});

it('imports with percentage coverage type', function () {
    actingAs($this->user);

    Excel::fake();

    // Mock Excel data with percentage type
    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'percentage', '80', 'Standard coverage'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 1,
            'updated' => 0,
            'skipped' => 0,
        ],
    ]);

    $rule = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect($rule)->not->toBeNull();
    expect($rule->coverage_type)->toBe('percentage');
    expect((float) $rule->coverage_value)->toBe(80.0);
    expect((float) $rule->patient_copay_percentage)->toBe(20.0);
    expect($rule->is_covered)->toBeTrue();
});

it('imports with fixed_amount coverage type', function () {
    actingAs($this->user);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['INSULIN', 'Insulin', '50.00', 'fixed_amount', '30', 'Fixed copay'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();

    $rule = InsuranceCoverageRule::where('item_code', 'INSULIN')->first();
    expect($rule)->not->toBeNull();
    expect($rule->coverage_type)->toBe('fixed'); // Database uses 'fixed' not 'fixed_amount'
    expect((float) $rule->coverage_value)->toBe(30.0);
    expect((float) $rule->patient_copay_percentage)->toBe(0.0);
    expect($rule->is_covered)->toBeTrue();
});

it('imports with full coverage type', function () {
    actingAs($this->user);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'full', '100', 'Fully covered'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();

    $rule = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect($rule)->not->toBeNull();
    expect($rule->coverage_type)->toBe('full');
    expect((float) $rule->coverage_value)->toBe(100.0);
    expect((float) $rule->patient_copay_percentage)->toBe(0.0);
    expect($rule->is_covered)->toBeTrue();
});

it('imports with excluded coverage type', function () {
    actingAs($this->user);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['AMOX250', 'Amoxicillin 250mg', '15.00', 'excluded', '0', 'Not covered'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();

    $rule = InsuranceCoverageRule::where('item_code', 'AMOX250')->first();
    expect($rule)->not->toBeNull();
    expect($rule->coverage_type)->toBe('excluded');
    expect((float) $rule->coverage_value)->toBe(0.0);
    expect((float) $rule->patient_copay_percentage)->toBe(100.0);
    expect($rule->is_covered)->toBeFalse();
});

it('returns specific error for invalid coverage_type', function () {
    actingAs($this->user);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'invalid_type', '80', ''],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 0,
            'updated' => 0,
            'skipped' => 1,
        ],
    ]);

    $errors = $response->json('results.errors');
    expect($errors)->toHaveCount(1);
    expect($errors[0]['error'])->toContain('Invalid coverage_type: invalid_type');
    expect($errors[0]['error'])->toContain('Must be: percentage, fixed_amount, full, excluded');
});

it('returns error with row number for invalid item_code', function () {
    actingAs($this->user);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['INVALID', 'Invalid Drug', '5.00', 'percentage', '80', ''],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 0,
            'updated' => 0,
            'skipped' => 1,
        ],
    ]);

    $errors = $response->json('results.errors');
    expect($errors)->toHaveCount(1);
    expect($errors[0]['row'])->toBe(2); // Row 2 (after header)
    expect($errors[0]['error'])->toContain('Item code INVALID not found in system');
});

it('shows correct import summary counts', function () {
    actingAs($this->user);

    // Create an existing rule to test update
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA500',
        'coverage_type' => 'percentage',
        'coverage_value' => 50,
    ]);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'percentage', '100', 'Updated'],
            ['AMOX250', 'Amoxicillin 250mg', '15.00', 'percentage', '90', 'New'],
            ['INVALID', 'Invalid Drug', '5.00', 'percentage', '80', 'Should skip'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 1,  // AMOX250
            'updated' => 1,  // PARA500
            'skipped' => 1,  // INVALID
        ],
    ]);

    expect($response->json('results.errors'))->toHaveCount(1);
});

it('handles multiple errors with row numbers', function () {
    actingAs($this->user);

    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['INVALID1', 'Invalid Drug 1', '5.00', 'percentage', '80', ''],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'invalid_type', '80', ''],
            ['INVALID2', 'Invalid Drug 2', '5.00', 'percentage', '80', ''],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 0,
            'updated' => 0,
            'skipped' => 3,
        ],
    ]);

    $errors = $response->json('results.errors');
    expect($errors)->toHaveCount(3);
    expect($errors[0]['row'])->toBe(2);
    expect($errors[1]['row'])->toBe(3);
    expect($errors[2]['row'])->toBe(4);
});

// Task 6.5: Backward compatibility tests

it('imports old format CSV with coverage_percentage', function () {
    actingAs($this->user);

    Excel::fake();

    // Old format with coverage_percentage column
    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'coverage_percentage', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '80', 'Old format'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 1,
            'updated' => 0,
            'skipped' => 0,
        ],
    ]);

    $rule = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect($rule)->not->toBeNull();
    expect($rule->coverage_type)->toBe('percentage');
    expect((float) $rule->coverage_value)->toBe(80.0);
    expect((float) $rule->patient_copay_percentage)->toBe(20.0);
});

it('converts old format to new format internally', function () {
    actingAs($this->user);

    Excel::fake();

    // Old format should be converted to percentage type
    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'coverage_percentage', 'notes'],
            ['AMOX250', 'Amoxicillin 250mg', '90', 'Converted'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();

    $rule = InsuranceCoverageRule::where('item_code', 'AMOX250')->first();
    expect($rule)->not->toBeNull();
    // Should be stored as percentage type
    expect($rule->coverage_type)->toBe('percentage');
    expect((float) $rule->coverage_value)->toBe(90.0);
    expect((float) $rule->patient_copay_percentage)->toBe(10.0);
});

it('new format takes precedence when both exist', function () {
    actingAs($this->user);

    Excel::fake();

    // File has both old and new format columns
    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'coverage_percentage', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '50', 'fixed_amount', '30', 'New format wins'],
        ]]);

    $file = UploadedFile::fake()->create('import.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();

    $rule = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect($rule)->not->toBeNull();
    // Should use new format (fixed_amount with value 30), not old format (percentage 50)
    expect($rule->coverage_type)->toBe('fixed');
    expect((float) $rule->coverage_value)->toBe(30.0);
    expect((float) $rule->patient_copay_percentage)->toBe(0.0);
});

it('old format works identically to new format for percentage', function () {
    actingAs($this->user);

    // Create two drugs
    Drug::factory()->create([
        'drug_code' => 'OLD001',
        'name' => 'Old Format Drug',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    Drug::factory()->create([
        'drug_code' => 'NEW001',
        'name' => 'New Format Drug',
        'unit_price' => 10.00,
        'is_active' => true,
    ]);

    Excel::fake();

    // Import with old format
    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'coverage_percentage', 'notes'],
            ['OLD001', 'Old Format Drug', '75', 'Old'],
        ]]);

    $file1 = UploadedFile::fake()->create('import1.xlsx');

    $response1 = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file1,
        'category' => 'drug',
    ]);

    $response1->assertSuccessful();

    // Import with new format
    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'coverage_type', 'coverage_value', 'notes'],
            ['NEW001', 'New Format Drug', 'percentage', '75', 'New'],
        ]]);

    $file2 = UploadedFile::fake()->create('import2.xlsx');

    $response2 = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file2,
        'category' => 'drug',
    ]);

    $response2->assertSuccessful();

    // Both should have identical coverage settings
    $oldRule = InsuranceCoverageRule::where('item_code', 'OLD001')->first();
    $newRule = InsuranceCoverageRule::where('item_code', 'NEW001')->first();

    expect($oldRule->coverage_type)->toBe($newRule->coverage_type);
    expect((float) $oldRule->coverage_value)->toBe((float) $newRule->coverage_value);
    expect((float) $oldRule->patient_copay_percentage)->toBe((float) $newRule->patient_copay_percentage);
});

// Task 6.6: Integration test for complete workflow

it('completes full workflow from download to upload with existing rules', function () {
    actingAs($this->user);

    // Step 1: Create existing coverage rules
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA500',
        'coverage_type' => 'percentage',
        'coverage_value' => 80,
        'notes' => 'Original rule',
    ]);

    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'AMOX250',
        'coverage_type' => 'fixed',
        'coverage_value' => 20,
        'notes' => 'Original fixed',
    ]);

    // Step 2: Download template (verify it includes pre-filled values)
    $template = new \App\Exports\CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    // Verify template has all drugs
    expect($collection)->toHaveCount(3); // PARA500, AMOX250, INSULIN

    // Verify pre-filled values
    $para = $collection->firstWhere('item_code', 'PARA500');
    expect($para['coverage_type'])->toBe('percentage');
    expect((float) $para['coverage_value'])->toBe(80.0);
    expect($para['notes'])->toBe('Original rule');

    $amox = $collection->firstWhere('item_code', 'AMOX250');
    expect($amox['coverage_type'])->toBe('fixed_amount'); // Mapped from 'fixed'
    expect((float) $amox['coverage_value'])->toBe(20.0);

    $insulin = $collection->firstWhere('item_code', 'INSULIN');
    expect($insulin['coverage_type'])->toBe('percentage'); // Default
    expect((float) $insulin['coverage_value'])->toBe(80.0); // Default

    // Step 3: Simulate user modifying template and uploading
    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'full', '100', 'Updated to full'],
            ['AMOX250', 'Amoxicillin 250mg', '15.00', 'percentage', '90', 'Changed to percentage'],
            ['INSULIN', 'Insulin', '50.00', 'fixed_amount', '30', 'New rule'],
        ]]);

    $file = UploadedFile::fake()->create('modified_template.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    // Step 4: Verify import results
    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 1,  // INSULIN
            'updated' => 2,  // PARA500, AMOX250
            'skipped' => 0,
        ],
    ]);

    // Step 5: Verify rules were created/updated correctly
    $paraRule = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect($paraRule->coverage_type)->toBe('full');
    expect((float) $paraRule->coverage_value)->toBe(100.0);
    expect((float) $paraRule->patient_copay_percentage)->toBe(0.0);
    expect($paraRule->notes)->toBe('Updated to full');

    $amoxRule = InsuranceCoverageRule::where('item_code', 'AMOX250')->first();
    expect($amoxRule->coverage_type)->toBe('percentage');
    expect((float) $amoxRule->coverage_value)->toBe(90.0);
    expect((float) $amoxRule->patient_copay_percentage)->toBe(10.0);
    expect($amoxRule->notes)->toBe('Changed to percentage');

    $insulinRule = InsuranceCoverageRule::where('item_code', 'INSULIN')->first();
    expect($insulinRule->coverage_type)->toBe('fixed');
    expect((float) $insulinRule->coverage_value)->toBe(30.0);
    expect((float) $insulinRule->patient_copay_percentage)->toBe(0.0);
    expect($insulinRule->notes)->toBe('New rule');

    // Step 6: Verify all rules are active and have correct plan
    $allRules = InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
        ->where('coverage_category', 'drug')
        ->whereNotNull('item_code')
        ->get();

    expect($allRules)->toHaveCount(3);
    expect($allRules->every(fn ($rule) => $rule->is_active))->toBeTrue();
    expect($allRules->every(fn ($rule) => $rule->is_covered || $rule->coverage_type === 'excluded'))->toBeTrue();
});

it('handles complete workflow with no existing rules', function () {
    actingAs($this->user);

    // Step 1: Download template with no existing rules
    $template = new \App\Exports\CoverageExceptionTemplate('drug', $this->plan->id);
    $sheets = $template->sheets();
    $dataSheet = $sheets[1];
    $collection = $dataSheet->collection();

    // All items should have default values
    expect($collection)->toHaveCount(3);
    expect($collection->every(fn ($item) => $item['coverage_type'] === 'percentage'))->toBeTrue();
    expect($collection->every(fn ($item) => (float) $item['coverage_value'] === 80.0))->toBeTrue();

    // Step 2: Upload with new rules
    Excel::fake();

    Excel::shouldReceive('toArray')
        ->once()
        ->andReturn([[
            ['item_code', 'item_name', 'current_price', 'coverage_type', 'coverage_value', 'notes'],
            ['PARA500', 'Paracetamol 500mg', '5.00', 'full', '100', 'Essential'],
            ['AMOX250', 'Amoxicillin 250mg', '15.00', 'percentage', '85', 'Standard'],
        ]]);

    $file = UploadedFile::fake()->create('new_rules.xlsx');

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'results' => [
            'created' => 2,
            'updated' => 0,
            'skipped' => 0,
        ],
    ]);

    // Verify rules were created
    expect(InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
        ->where('coverage_category', 'drug')
        ->whereNotNull('item_code')
        ->count())->toBe(2);
});
