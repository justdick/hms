<?php

/**
 * Feature Test for NHIS Coverage CSV Workflow
 *
 * Tests the complete NHIS coverage CSV export/import workflow including:
 * - Downloading NHIS coverage template with pre-filled Master prices
 * - Importing NHIS coverage CSV (only copay amounts saved)
 * - Verifying tariff values from CSV are ignored
 *
 * Requirements: 6.1, 6.2, 6.3, 6.4
 */

use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    // Clean up existing data
    InsuranceCoverageRule::query()->delete();
    NhisItemMapping::query()->delete();
    NhisTariff::query()->delete();
    Drug::query()->delete();
});

describe('NHIS Coverage Template Download', function () {
    it('allows authenticated user to download NHIS coverage template', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        // Create some drugs
        Drug::factory()->count(3)->create(['is_active' => true]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('admin.insurance.nhis-coverage.template', [
                'plan' => $nhisPlan->id,
                'category' => 'drug',
            ]));

        // Assert
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('rejects template download for non-NHIS plans', function () {
        // Arrange
        $user = User::factory()->create();

        $regularProvider = InsuranceProvider::factory()->create(['is_nhis' => false]);
        $regularPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $regularProvider->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('admin.insurance.nhis-coverage.template', [
                'plan' => $regularPlan->id,
                'category' => 'drug',
            ]));

        // Assert
        $response->assertStatus(400);
        $response->assertJson(['error' => 'This plan is not an NHIS plan']);
    });

    it('rejects template download for invalid category', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get(route('admin.insurance.nhis-coverage.template', [
                'plan' => $nhisPlan->id,
                'category' => 'invalid_category',
            ]));

        // Assert
        $response->assertStatus(400);
        $response->assertJson(['error' => 'Invalid category']);
    });

    it('requires authentication to download template', function () {
        // Arrange
        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        // Act - No user authenticated
        $response = $this->get(route('admin.insurance.nhis-coverage.template', [
            'plan' => $nhisPlan->id,
            'category' => 'drug',
        ]));

        // Assert - Should redirect to login
        $response->assertRedirect(route('login'));
    });
});

describe('NHIS Coverage Import', function () {
    it('imports NHIS coverage CSV and saves only copay amounts', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        // Create drugs
        $drug1 = Drug::factory()->create(['is_active' => true]);
        $drug2 = Drug::factory()->create(['is_active' => true]);

        // Create CSV content
        $csvContent = "item_code,item_name,hospital_price,nhis_tariff_price,copay_amount\n";
        $csvContent .= "{$drug1->drug_code},{$drug1->name},100.00,75.00,10.00\n";
        $csvContent .= "{$drug2->drug_code},{$drug2->name},150.00,120.00,15.00\n";

        $file = UploadedFile::fake()->createWithContent('nhis_coverage.csv', $csvContent);

        // Act
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
                'file' => $file,
                'category' => 'drug',
            ]);

        // Assert
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'results' => [
                'created' => 2,
                'updated' => 0,
                'skipped' => 0,
            ],
        ]);

        // Verify rules were created with correct copay amounts
        $rule1 = InsuranceCoverageRule::where('item_code', $drug1->drug_code)->first();
        $rule2 = InsuranceCoverageRule::where('item_code', $drug2->drug_code)->first();

        expect($rule1)->not->toBeNull();
        expect((float) $rule1->patient_copay_amount)->toBe(10.00);
        expect($rule1->tariff_amount)->toBeNull(); // Tariff should NOT be saved

        expect($rule2)->not->toBeNull();
        expect((float) $rule2->patient_copay_amount)->toBe(15.00);
        expect($rule2->tariff_amount)->toBeNull(); // Tariff should NOT be saved
    });

    it('rejects import for non-NHIS plans', function () {
        // Arrange
        $user = User::factory()->create();

        $regularProvider = InsuranceProvider::factory()->create(['is_nhis' => false]);
        $regularPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $regularProvider->id,
        ]);

        $drug = Drug::factory()->create(['is_active' => true]);

        $csvContent = "item_code,item_name,hospital_price,nhis_tariff_price,copay_amount\n";
        $csvContent .= "{$drug->drug_code},{$drug->name},100.00,75.00,10.00\n";

        $file = UploadedFile::fake()->createWithContent('nhis_coverage.csv', $csvContent);

        // Act
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $regularPlan->id]), [
                'file' => $file,
                'category' => 'drug',
            ]);

        // Assert
        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'This plan is not an NHIS plan',
        ]);
    });

    it('validates file is required', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
                'category' => 'drug',
            ]);

        // Assert
        $response->assertSessionHasErrors('file');
    });

    it('validates category is required', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        $file = UploadedFile::fake()->create('test.csv');

        // Act
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
                'file' => $file,
            ]);

        // Assert
        $response->assertSessionHasErrors('category');
    });

    it('updates existing coverage rules on re-import', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        $drug = Drug::factory()->create(['is_active' => true]);

        // Create existing rule
        InsuranceCoverageRule::factory()->create([
            'insurance_plan_id' => $nhisPlan->id,
            'coverage_category' => 'drug',
            'item_code' => $drug->drug_code,
            'patient_copay_amount' => 5.00,
        ]);

        // Create CSV with new copay
        $csvContent = "item_code,item_name,hospital_price,nhis_tariff_price,copay_amount\n";
        $csvContent .= "{$drug->drug_code},{$drug->name},100.00,75.00,20.00\n";

        $file = UploadedFile::fake()->createWithContent('nhis_coverage.csv', $csvContent);

        // Act
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
                'file' => $file,
                'category' => 'drug',
            ]);

        // Assert
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'results' => [
                'created' => 0,
                'updated' => 1,
            ],
        ]);

        // Verify copay was updated
        $rule = InsuranceCoverageRule::where('item_code', $drug->drug_code)->first();
        expect((float) $rule->patient_copay_amount)->toBe(20.00);
    });

    it('skips rows with invalid item codes', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        $validDrug = Drug::factory()->create(['is_active' => true]);

        // Create CSV with one valid and one invalid item
        $csvContent = "item_code,item_name,hospital_price,nhis_tariff_price,copay_amount\n";
        $csvContent .= "{$validDrug->drug_code},{$validDrug->name},100.00,75.00,10.00\n";
        $csvContent .= "INVALID_CODE,Invalid Drug,50.00,40.00,5.00\n";

        $file = UploadedFile::fake()->createWithContent('nhis_coverage.csv', $csvContent);

        // Act
        $response = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
                'file' => $file,
                'category' => 'drug',
            ]);

        // Assert
        $response->assertOk();
        $responseData = $response->json();

        expect($responseData['results']['created'])->toBe(1);
        expect($responseData['results']['skipped'])->toBe(1);
        expect($responseData['results']['errors'])->toHaveCount(1);
    });

    it('requires authentication to import', function () {
        // Arrange
        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        $file = UploadedFile::fake()->create('test.csv');

        // Act - No user authenticated
        $response = $this->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
            'file' => $file,
            'category' => 'drug',
        ]);

        // Assert - Should redirect to login
        $response->assertRedirect(route('login'));
    });
});

describe('Complete NHIS Coverage Workflow', function () {
    it('completes full export-edit-import cycle correctly', function () {
        // Arrange
        $user = User::factory()->create();

        $nhisProvider = InsuranceProvider::factory()->nhis()->create();
        $nhisPlan = InsurancePlan::factory()->create([
            'insurance_provider_id' => $nhisProvider->id,
        ]);

        // Create drugs with NHIS mappings
        $drug = Drug::factory()->create([
            'unit_price' => 100.00,
            'is_active' => true,
        ]);

        $nhisTariff = NhisTariff::factory()->medicine()->create([
            'price' => 75.00,
            'is_active' => true,
        ]);

        NhisItemMapping::factory()->create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        // Step 1: Download template
        $downloadResponse = $this->actingAs($user)
            ->get(route('admin.insurance.nhis-coverage.template', [
                'plan' => $nhisPlan->id,
                'category' => 'drug',
            ]));

        $downloadResponse->assertOk();

        // Step 2: Simulate editing the CSV (user adds copay)
        $csvContent = "item_code,item_name,hospital_price,nhis_tariff_price,copay_amount\n";
        $csvContent .= "{$drug->drug_code},{$drug->name},100.00,75.00,12.50\n";

        $file = UploadedFile::fake()->createWithContent('edited_coverage.csv', $csvContent);

        // Step 3: Import the edited CSV
        $importResponse = $this->actingAs($user)
            ->post(route('admin.insurance.nhis-coverage.import', ['plan' => $nhisPlan->id]), [
                'file' => $file,
                'category' => 'drug',
            ]);

        $importResponse->assertOk();
        $importResponse->assertJson(['success' => true]);

        // Step 4: Verify the result
        $rule = InsuranceCoverageRule::where('insurance_plan_id', $nhisPlan->id)
            ->where('coverage_category', 'drug')
            ->where('item_code', $drug->drug_code)
            ->first();

        expect($rule)->not->toBeNull();
        expect((float) $rule->patient_copay_amount)->toBe(12.50);
        expect($rule->tariff_amount)->toBeNull(); // Tariff from CSV should be ignored
        expect($rule->coverage_type)->toBe('full'); // NHIS uses full coverage
    });
});
