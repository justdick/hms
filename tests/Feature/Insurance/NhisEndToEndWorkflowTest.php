<?php

declare(strict_types=1);

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\LabService;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed permissions
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'nhis-tariffs.manage',
        'nhis-mappings.manage',
        'gdrg-tariffs.manage',
        'insurance.vet-claims',
        'insurance.view-batches',
        'insurance.manage-batches',
        'insurance.export-batches',
        'insurance.view-reports',
    ]);
});

describe('Complete NHIS Workflow', function () {
    it('completes full workflow: import tariffs → map items → check-in → create charges → vet claim → batch → export', function () {
        // Step 1: Create NHIS Provider and Plan
        $nhisProvider = InsuranceProvider::factory()->create([
            'name' => 'National Health Insurance Scheme',
            'is_nhis' => true,
        ]);

        $nhisPlan = InsurancePlan::factory()->for($nhisProvider, 'provider')->create([
            'plan_name' => 'NHIS Standard Plan',
        ]);

        // Step 2: Import NHIS Tariffs
        $nhisLabTariff = NhisTariff::factory()->create([
            'nhis_code' => 'LAB001',
            'name' => 'Complete Blood Count',
            'category' => 'lab',
            'price' => 50.00,
            'is_active' => true,
        ]);

        $nhisDrugTariff = NhisTariff::factory()->create([
            'nhis_code' => 'DRG001',
            'name' => 'Paracetamol 500mg',
            'category' => 'medicine',
            'price' => 5.00,
            'is_active' => true,
        ]);

        // Step 3: Create hospital items
        $labService = LabService::factory()->create([
            'name' => 'CBC Test',
            'code' => 'CBC001',
            'price' => 75.00, // Hospital price
        ]);

        $drug = Drug::factory()->create([
            'name' => 'Paracetamol',
            'drug_code' => 'PARA001',
            'unit_price' => 8.00, // Hospital price
        ]);

        // Step 4: Map hospital items to NHIS tariffs
        NhisItemMapping::create([
            'item_type' => 'lab_service',
            'item_id' => $labService->id,
            'item_code' => $labService->code,
            'nhis_tariff_id' => $nhisLabTariff->id,
        ]);

        NhisItemMapping::create([
            'item_type' => 'drug',
            'item_id' => $drug->id,
            'item_code' => $drug->drug_code,
            'nhis_tariff_id' => $nhisDrugTariff->id,
        ]);

        // Step 5: Create patient with NHIS insurance
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $nhisPlan->id,
            'membership_id' => 'NHIS-2025-001234',
            'coverage_start_date' => now()->subYear(),
            'coverage_end_date' => now()->addYear(),
            'status' => 'active',
        ]);

        // Step 6: Create check-in
        $department = Department::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);

        // Get the patient insurance record
        $patientInsurance = PatientInsurance::where('patient_id', $patient->id)
            ->where('insurance_plan_id', $nhisPlan->id)
            ->first();

        // Step 7: Create insurance claim with items
        $claim = InsuranceClaim::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'patient_insurance_id' => $patientInsurance->id,
            'patient_id' => $patient->id,
            'status' => 'pending_vetting',
        ]);

        // Add lab service item
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'lab',
            'code' => $labService->code,
            'description' => $labService->name,
            'quantity' => 1,
            'unit_tariff' => $labService->price,
            'subtotal' => $labService->price,
        ]);

        // Add drug item
        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'code' => $drug->drug_code,
            'description' => $drug->name,
            'quantity' => 10,
            'unit_tariff' => $drug->unit_price,
            'subtotal' => $drug->unit_price * 10,
        ]);

        // Step 8: Create G-DRG tariff and add diagnosis
        $gdrgTariff = GdrgTariff::factory()->create([
            'code' => 'GDRG-001',
            'name' => 'General Medical Care',
            'tariff_price' => 200.00,
            'is_active' => true,
        ]);

        $diagnosis = Diagnosis::factory()->create([
            'code' => 'J06.9',
            'diagnosis' => 'Acute upper respiratory infection',
        ]);

        // Step 9: Vet the claim
        $response = $this->actingAs($this->admin)
            ->postJson("/admin/insurance/claims/{$claim->id}/vet", [
                'action' => 'approve',
                'gdrg_tariff_id' => $gdrgTariff->id,
                'diagnoses' => [
                    ['diagnosis_id' => $diagnosis->id, 'is_primary' => true],
                ],
            ]);

        $response->assertRedirect();

        // Verify claim is vetted
        $claim->refresh();
        expect($claim->status)->toBe('vetted');
        expect($claim->gdrg_tariff_id)->toBe($gdrgTariff->id);
        expect($claim->gdrg_amount)->toBe('200.00');
        expect($claim->vetted_by)->toBe($this->admin->id);
        expect($claim->vetted_at)->not->toBeNull();

        // Verify NHIS prices are stored on items
        $labItem = $claim->items()->where('item_type', 'lab')->first();
        expect($labItem->nhis_price)->toBe('50.00'); // NHIS tariff price

        $drugItem = $claim->items()->where('item_type', 'drug')->first();
        expect($drugItem->nhis_price)->toBe('5.00'); // NHIS tariff price per unit

        // Step 10: Create batch and add claim
        $batch = ClaimBatch::factory()->create([
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/insurance/batches/{$batch->id}/claims", [
                'claim_ids' => [$claim->id],
            ]);

        $response->assertRedirect();

        // Verify claim is in batch via ClaimBatchItem
        $batchItem = ClaimBatchItem::where('claim_batch_id', $batch->id)
            ->where('insurance_claim_id', $claim->id)
            ->first();
        expect($batchItem)->not->toBeNull();

        // Step 11: Export batch as XML
        $response = $this->actingAs($this->admin)
            ->get("/admin/insurance/batches/{$batch->id}/export");

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/xml');

        $xml = $response->getContent();

        // Verify XML contains expected data
        expect($xml)->toContain('GDRG-001'); // G-DRG code
        expect($xml)->toContain('LAB001'); // Lab NHIS code
        expect($xml)->toContain('DRG001'); // Drug NHIS code
    });
});

describe('NHIS Coverage Calculation', function () {
    it('calculates NHIS coverage using Master price plus copay only', function () {
        // Step 1: Create NHIS Provider and Plan
        $nhisProvider = InsuranceProvider::factory()->create([
            'name' => 'NHIS',
            'is_nhis' => true,
        ]);

        $nhisPlan = InsurancePlan::factory()->for($nhisProvider, 'provider')->create([
            'plan_name' => 'NHIS Standard',
        ]);

        // Step 2: Create NHIS Tariff with specific price
        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'LAB-CBC',
            'name' => 'Complete Blood Count',
            'category' => 'lab',
            'price' => 45.00, // NHIS Master price
            'is_active' => true,
        ]);

        // Step 3: Create hospital lab service with different price
        $labService = LabService::factory()->create([
            'name' => 'CBC Test',
            'code' => 'CBC-001',
            'price' => 75.00, // Hospital price (higher than NHIS)
        ]);

        // Step 4: Map hospital item to NHIS tariff
        NhisItemMapping::create([
            'item_type' => 'lab_service',
            'item_id' => $labService->id,
            'item_code' => $labService->code,
            'nhis_tariff_id' => $nhisTariff->id,
        ]);

        // Step 5: Create coverage rule with copay
        \App\Models\InsuranceCoverageRule::create([
            'insurance_plan_id' => $nhisPlan->id,
            'coverage_category' => 'lab',
            'item_code' => $labService->code,
            'patient_copay_amount' => 4.50, // Fixed copay amount
        ]);

        // Step 6: Calculate coverage using the service
        $coverageService = app(\App\Services\InsuranceCoverageService::class);

        $coverage = $coverageService->calculateCoverage(
            $nhisPlan->id,
            'lab',
            $labService->code,
            (float) $labService->price, // Hospital price
            1, // quantity
            null, // date
            $labService->id, // itemId - required for NHIS lookup
            'lab_service' // itemType - required for NHIS lookup
        );

        // Verify NHIS coverage calculation:
        // - Insurance pays: NHIS Master price = 45.00
        // - Patient pays: Copay only (10% of NHIS price) = 4.50
        // - NOT based on hospital price
        expect($coverage['insurance_pays'])->toBe(45.00);
        expect($coverage['patient_pays'])->toBe(4.50);
        expect($coverage['is_covered'])->toBeTrue();
    });

    it('returns not covered for unmapped items in NHIS plan', function () {
        // Create NHIS Provider and Plan
        $nhisProvider = InsuranceProvider::factory()->create([
            'is_nhis' => true,
        ]);

        $nhisPlan = InsurancePlan::factory()->for($nhisProvider, 'provider')->create();

        // Create hospital item WITHOUT NHIS mapping
        $labService = LabService::factory()->create([
            'code' => 'UNMAPPED-001',
            'price' => 100.00,
        ]);

        // Calculate coverage
        $coverageService = app(\App\Services\InsuranceCoverageService::class);

        $coverage = $coverageService->calculateCoverage(
            $nhisPlan->id,
            'lab',
            $labService->code,
            (float) $labService->price,
            1,
            null, // date
            $labService->id, // itemId - required for NHIS lookup
            'lab_service' // itemType - required for NHIS lookup
        );

        // Unmapped items should not be covered by NHIS
        expect($coverage['is_covered'])->toBeFalse();
        expect($coverage['insurance_pays'])->toBe(0.0);
        expect($coverage['patient_pays'])->toBe(100.00);
    });
});

describe('Batch Submission Workflow', function () {
    it('completes batch workflow: create → add claims → finalize → export → submit → record response', function () {
        // Create NHIS Provider and Plan
        $nhisProvider = InsuranceProvider::factory()->create(['is_nhis' => true]);
        $nhisPlan = InsurancePlan::factory()->for($nhisProvider, 'provider')->create();

        // Create patient insurance
        $patient = Patient::factory()->create();
        $patientInsurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $nhisPlan->id,
            'status' => 'active',
        ]);

        // Create G-DRG tariff
        $gdrgTariff = GdrgTariff::factory()->create([
            'tariff_price' => 150.00,
            'is_active' => true,
        ]);

        // Create vetted claims
        $claims = InsuranceClaim::factory()->count(3)->create([
            'patient_insurance_id' => $patientInsurance->id,
            'patient_id' => $patient->id,
            'status' => 'vetted',
            'gdrg_tariff_id' => $gdrgTariff->id,
            'gdrg_amount' => 150.00,
            'total_claim_amount' => 200.00,
        ]);

        // Step 1: Create batch
        $response = $this->actingAs($this->admin)
            ->postJson('/admin/insurance/batches', [
                'name' => 'November 2025 Batch',
                'submission_period' => '2025-11',
            ]);

        $response->assertRedirect();

        $batch = ClaimBatch::latest()->first();
        expect($batch)->not->toBeNull();
        expect($batch->name)->toBe('November 2025 Batch');
        expect($batch->status)->toBe('draft');

        // Step 2: Add claims to batch
        $response = $this->actingAs($this->admin)
            ->postJson("/admin/insurance/batches/{$batch->id}/claims", [
                'claim_ids' => $claims->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();

        // Verify claims are in batch
        $batch->refresh();
        expect($batch->batchItems()->count())->toBe(3);
        expect($batch->total_claims)->toBe(3);

        // Step 3: Finalize batch
        $response = $this->actingAs($this->admin)
            ->postJson("/admin/insurance/batches/{$batch->id}/finalize");

        $response->assertRedirect();

        $batch->refresh();
        expect($batch->status)->toBe('finalized');

        // Step 4: Export batch as XML
        $response = $this->actingAs($this->admin)
            ->get("/admin/insurance/batches/{$batch->id}/export");

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/xml');

        // Verify export timestamp is recorded
        $batch->refresh();
        expect($batch->exported_at)->not->toBeNull();

        // Step 5: Mark batch as submitted
        $this->admin->givePermissionTo('insurance.submit-batches');

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/insurance/batches/{$batch->id}/submit", [
                'submitted_at' => now()->toDateTimeString(),
            ]);

        $response->assertRedirect();

        $batch->refresh();
        expect($batch->status)->toBe('submitted');
        expect($batch->submitted_at)->not->toBeNull();

        // Step 6: Record NHIA response
        $this->admin->givePermissionTo('insurance.record-batch-responses');

        $responses = [];
        foreach ($claims as $index => $claim) {
            $responses[$claim->id] = [
                'status' => $index < 2 ? 'approved' : 'rejected',
                'approved_amount' => $index < 2 ? 180.00 : 0,
                'rejection_reason' => $index >= 2 ? 'Invalid diagnosis code' : null,
            ];
        }

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/insurance/batches/{$batch->id}/response", [
                'responses' => $responses,
            ]);

        $response->assertRedirect();

        // Verify claim statuses are updated
        $claims[0]->refresh();
        $claims[1]->refresh();
        $claims[2]->refresh();

        expect($claims[0]->status)->toBe('approved');
        expect($claims[1]->status)->toBe('approved');
        expect($claims[2]->status)->toBe('rejected');
        expect($claims[2]->rejection_reason)->toBe('Invalid diagnosis code');
    });
});
