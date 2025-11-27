<?php

/**
 * Feature tests for ClaimExportController
 *
 * Tests the XML export functionality for NHIA claim batch submission.
 *
 * _Requirements: 15.1, 15.2, 15.3_
 */

use App\Models\ClaimBatch;
use App\Models\ClaimBatchItem;
use App\Models\Diagnosis;
use App\Models\GdrgTariff;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimDiagnosis;
use App\Models\InsuranceClaimItem;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'insurance.view-batches']);
    Permission::firstOrCreate(['name' => 'insurance.manage-batches']);
    Permission::firstOrCreate(['name' => 'insurance.export-batches']);
});

describe('exportXml', function () {
    it('exports a batch to XML format', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'batch_number' => 'BATCH-202511-0001',
            'name' => 'November 2025 Claims',
            'submission_period' => '2025-11-01',
            'status' => 'finalized',
            'total_claims' => 1,
            'total_amount' => 500.00,
        ]);

        $claim = InsuranceClaim::factory()->create([
            'status' => 'vetted',
            'total_claim_amount' => 500.00,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
            'claim_amount' => 500.00,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertHeader('Content-Disposition', 'attachment; filename="nhis-batch-BATCH-202511-0001.xml"');

        // Verify XML content
        $xml = $response->getContent();
        expect($xml)->toContain('NHIAClaimBatch');
        expect($xml)->toContain('BATCH-202511-0001');
        expect($xml)->toContain('November 2025 Claims');
    });

    it('records export timestamp when exporting', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'status' => 'finalized',
            'exported_at' => null,
        ]);

        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $batch->refresh();
        expect($batch->exported_at)->not->toBeNull();
    });

    it('includes facility information in XML', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<Facility>');
        expect($xml)->toContain('<FacilityCode>');
        expect($xml)->toContain('<FacilityName>');
    });

    it('includes batch details in XML', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'batch_number' => 'BATCH-TEST-001',
            'name' => 'Test Batch',
            'submission_period' => '2025-11-01',
            'status' => 'finalized',
            'total_claims' => 2,
            'total_amount' => 1000.00,
        ]);

        for ($i = 0; $i < 2; $i++) {
            $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
            ClaimBatchItem::factory()->create([
                'claim_batch_id' => $batch->id,
                'insurance_claim_id' => $claim->id,
            ]);
        }

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<BatchDetails>');
        expect($xml)->toContain('<BatchNumber>BATCH-TEST-001</BatchNumber>');
        expect($xml)->toContain('<BatchName>Test Batch</BatchName>');
        expect($xml)->toContain('<TotalClaims>2</TotalClaims>');
        expect($xml)->toContain('<TotalAmount>1000.00</TotalAmount>');
    });

    it('includes patient NHIS member ID in XML', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $patient = Patient::factory()->create();

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        // NHIS member ID comes from claim's membership_id field
        $claim = InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'membership_id' => 'NHIS-12345678',
            'status' => 'vetted',
        ]);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<NhisMemberId>NHIS-12345678</NhisMemberId>');
    });

    it('includes G-DRG code in XML', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $gdrgTariff = GdrgTariff::factory()->create([
            'code' => 'GDRG-OPD001',
            'name' => 'General OPD Consultation',
            'tariff_price' => 150.00,
        ]);

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create([
            'gdrg_tariff_id' => $gdrgTariff->id,
            'gdrg_amount' => 150.00,
            'status' => 'vetted',
        ]);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<GDRG>');
        expect($xml)->toContain('<Code>GDRG-OPD001</Code>');
        expect($xml)->toContain('<Name>General OPD Consultation</Name>');
        expect($xml)->toContain('<Amount>150.00</Amount>');
    });

    it('includes diagnoses with ICD-10 codes in XML', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $diagnosis = Diagnosis::factory()->create([
            'diagnosis' => 'Malaria',
            'icd_10' => 'B50.9',
        ]);

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);

        InsuranceClaimDiagnosis::factory()->create([
            'insurance_claim_id' => $claim->id,
            'diagnosis_id' => $diagnosis->id,
            'is_primary' => true,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<Diagnoses>');
        expect($xml)->toContain('<ICD10Code>B50.9</ICD10Code>');
        expect($xml)->toContain('<Description>Malaria</Description>');
    });

    it('includes item NHIS codes and prices in XML', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $nhisTariff = NhisTariff::factory()->create([
            'nhis_code' => 'NHIS-MED-001',
            'price' => 50.00,
        ]);

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);

        InsuranceClaimItem::factory()->create([
            'insurance_claim_id' => $claim->id,
            'item_type' => 'drug',
            'code' => 'HOSP-001',
            'description' => 'Paracetamol 500mg',
            'quantity' => 10,
            'nhis_tariff_id' => $nhisTariff->id,
            'nhis_code' => 'NHIS-MED-001',
            'nhis_price' => 50.00,
            'unit_tariff' => 50.00,
            'subtotal' => 500.00,
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $xml = $response->getContent();
        expect($xml)->toContain('<Items>');
        expect($xml)->toContain('<NhisCode>NHIS-MED-001</NhisCode>');
        expect($xml)->toContain('<Description>Paracetamol 500mg</Description>');
        expect($xml)->toContain('<Quantity>10</Quantity>');
    });

    it('generates valid XML structure', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - Valid XML
        $xml = $response->getContent();
        $dom = new DOMDocument;
        $isValid = $dom->loadXML($xml);
        expect($isValid)->toBeTrue();

        // Check root element
        expect($dom->documentElement->tagName)->toBe('NHIAClaimBatch');
        expect($dom->documentElement->getAttribute('xmlns'))->toBe('http://nhia.gov.gh/claims');
    });

    it('denies export to unauthorized user', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'finalized']);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $response->assertForbidden();
    });

    it('allows export of draft batch', function () {
        // Arrange - Export should work for any batch status
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create(['status' => 'draft']);
        $claim = InsuranceClaim::factory()->create(['status' => 'vetted']);
        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');
    });

    it('handles special characters in data', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.export-batches');

        $batch = ClaimBatch::factory()->create([
            'name' => 'Test & Special <Characters> "Batch"',
            'status' => 'finalized',
        ]);

        $claim = InsuranceClaim::factory()->create([
            'patient_surname' => "O'Brien",
            'patient_other_names' => 'John & Jane',
            'status' => 'vetted',
        ]);

        ClaimBatchItem::factory()->create([
            'claim_batch_id' => $batch->id,
            'insurance_claim_id' => $claim->id,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->get("/admin/insurance/claims/export-batch/{$batch->id}");

        // Assert - XML should be valid despite special characters
        $xml = $response->getContent();
        $dom = new DOMDocument;
        $isValid = $dom->loadXML($xml);
        expect($isValid)->toBeTrue();
    });
});
