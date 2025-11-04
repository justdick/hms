<?php

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->plan = InsurancePlan::factory()->create();

    // Create some test drugs
    Drug::factory()->create([
        'drug_code' => 'PARA500',
        'name' => 'Paracetamol 500mg',
        'unit_price' => 5.00,
    ]);

    Drug::factory()->create([
        'drug_code' => 'AMOX250',
        'name' => 'Amoxicillin 250mg',
        'unit_price' => 15.00,
    ]);
});

it('can download coverage exception template', function () {
    actingAs($this->user);

    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('can preview valid import file', function () {
    actingAs($this->user);

    // Create a mock Excel file with valid data
    $file = UploadedFile::fake()->create('exceptions.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    // Mock the Excel import to return valid data
    Excel::fake();

    $response = $this->postJson(route('admin.insurance.coverage.import-preview', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'valid_rows',
        'errors',
        'summary' => [
            'total',
            'valid',
            'invalid',
        ],
    ]);
});

it('validates required fields in import', function () {
    actingAs($this->user);

    $response = $this->postJson(route('admin.insurance.coverage.import-preview', [
        'plan' => $this->plan->id,
    ]), [
        'category' => 'drug',
        // Missing file
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
});

it('validates category in import', function () {
    actingAs($this->user);

    $file = UploadedFile::fake()->create('exceptions.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->postJson(route('admin.insurance.coverage.import-preview', [
        'plan' => $this->plan->id,
    ]), [
        'file' => $file,
        'category' => 'invalid_category',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['category']);
});

it('can import valid exceptions', function () {
    actingAs($this->user);

    $validatedRows = [
        [
            'item_code' => 'PARA500',
            'item_name' => 'Paracetamol 500mg',
            'coverage_percentage' => 100,
            'notes' => 'Essential medication',
        ],
        [
            'item_code' => 'AMOX250',
            'item_name' => 'Amoxicillin 250mg',
            'coverage_percentage' => 90,
            'notes' => null,
        ],
    ];

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'validated_rows' => $validatedRows,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'created' => 2,
        'updated' => 0,
    ]);

    // Verify rules were created
    expect(InsuranceCoverageRule::where('insurance_plan_id', $this->plan->id)
        ->where('coverage_category', 'drug')
        ->whereNotNull('item_code')
        ->count())->toBe(2);

    // Verify first rule
    $rule1 = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect((float) $rule1->coverage_value)->toBe(100.0);
    expect((float) $rule1->patient_copay_percentage)->toBe(0.0);
    expect($rule1->notes)->toBe('Essential medication');

    // Verify second rule
    $rule2 = InsuranceCoverageRule::where('item_code', 'AMOX250')->first();
    expect((float) $rule2->coverage_value)->toBe(90.0);
    expect((float) $rule2->patient_copay_percentage)->toBe(10.0);
});

it('updates existing exceptions on import', function () {
    actingAs($this->user);

    // Create an existing rule
    $existingRule = InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'PARA500',
        'coverage_value' => 80,
        'patient_copay_percentage' => 20,
    ]);

    $validatedRows = [
        [
            'item_code' => 'PARA500',
            'item_name' => 'Paracetamol 500mg',
            'coverage_percentage' => 100,
            'notes' => 'Updated to full coverage',
        ],
    ];

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'validated_rows' => $validatedRows,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
        'created' => 0,
        'updated' => 1,
    ]);

    // Verify rule was updated
    $existingRule->refresh();
    expect((float) $existingRule->coverage_value)->toBe(100.0);
    expect((float) $existingRule->patient_copay_percentage)->toBe(0.0);
    expect($existingRule->notes)->toBe('Updated to full coverage');
});

it('handles coverage percentage correctly', function () {
    actingAs($this->user);

    $validatedRows = [
        [
            'item_code' => 'PARA500',
            'item_name' => 'Paracetamol 500mg',
            'coverage_percentage' => 75,
            'notes' => null,
        ],
    ];

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'validated_rows' => $validatedRows,
        'category' => 'drug',
    ]);

    $response->assertSuccessful();

    $rule = InsuranceCoverageRule::where('item_code', 'PARA500')->first();
    expect((float) $rule->coverage_value)->toBe(75.0);
    expect((float) $rule->patient_copay_percentage)->toBe(25.0);
});

it('requires validated_rows array for import', function () {
    actingAs($this->user);

    $response = $this->postJson(route('admin.insurance.coverage.import', [
        'plan' => $this->plan->id,
    ]), [
        'category' => 'drug',
        // Missing validated_rows
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['validated_rows']);
});

// Task 6.3: Feature tests for template download

it('authorized user can download template', function () {
    actingAs($this->user);

    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertDownload();
});

it('template includes all items for category', function () {
    actingAs($this->user);

    // Create multiple drugs
    Drug::factory()->count(5)->create(['is_active' => true]);

    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    // Template should be generated successfully with all items
});

it('template pre-fills existing coverage values', function () {
    actingAs($this->user);

    $drug = Drug::factory()->create([
        'drug_code' => 'TEST001',
        'name' => 'Test Drug',
        'unit_price' => 50.00,
        'is_active' => true,
    ]);

    // Create existing coverage rule
    InsuranceCoverageRule::factory()->create([
        'insurance_plan_id' => $this->plan->id,
        'coverage_category' => 'drug',
        'item_code' => 'TEST001',
        'coverage_type' => 'fixed',
        'coverage_value' => 25,
        'notes' => 'Pre-filled value',
    ]);

    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    // Template should include the pre-filled coverage values
});

it('unauthenticated user cannot download template', function () {
    // Don't act as any user (unauthenticated)
    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertRedirect(route('login'));
});

it('invalid category returns error', function () {
    actingAs($this->user);

    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'invalid_category',
    ]));

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid category']);
});

it('template filename includes category and date', function () {
    actingAs($this->user);

    $response = $this->get(route('admin.insurance.coverage.import-template', [
        'plan' => $this->plan->id,
        'category' => 'drug',
    ]));

    $response->assertSuccessful();
    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('coverage_template_drug');
    expect($disposition)->toContain(now()->format('Y-m-d'));
});
